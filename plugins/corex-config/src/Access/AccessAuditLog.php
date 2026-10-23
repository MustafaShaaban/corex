<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AccessRefusalRecorder;
use Corex\Admin\AdminPage;

/**
 * The access audit log (spec 067, design: "Corex Access & Abilities" → Audit log). Records REAL
 * access refusals only: permission-denied attempts on CoreX screens, published through the
 * `corex_admin_access_denied` action by {@see \Corex\Admin\AdminPage::permissionDenied()} and
 * {@see AccessDeniedGate}. Access requests and decisions are in the shared activity stream.
 *
 * One person's refusals of one section are one entry for an hour from the first of them, with a
 * count and the first and last time (spec 112, FR-009 to FR-012), so a page reloaded a thousand
 * times is one line and does not push anybody else's out. Each person keeps at most
 * {@see MAX_PER_PERSON} entries before the log's own cap applies.
 *
 * Where the host has a persistent object cache, a repeat within the hour is counted there and the
 * database is written once for the window: when it starts, and again when the next refusal after
 * it closes it or {@see settle()} runs. Without one, a repeat updates that person's entry in place.
 * Entries live in an autoload-off option, pruned to 30 days by their last refusal (DECISIONS #114).
 */
final class AccessAuditLog implements AccessRefusalRecorder
{
    public const MAX_PER_PERSON = 20;

    private const OPTION         = 'corex_access_audit_log';
    private const CACHE_GROUP    = 'corex_access_audit';
    private const MAX_ENTRIES    = 100;
    private const WINDOW_SECONDS = 3600;
    private const RETENTION_DAYS = 30;
    private const DAY_SECONDS    = 86400;

    public function register(): void
    {
        add_action('corex_admin_access_denied', [$this, 'recordDenied']);
        add_action('corex_access_refused', [$this, 'recordRefusedAction'], 10, 2);
    }

    /**
     * Action callback: a CoreX route or form refused the current user for want of its screen's
     * ability (spec 117, FR-005). Recorded under the screen's page slug, counted by the same rule.
     */
    public function recordRefusedAction(string $kind, string $section): void
    {
        $this->record(
            $kind,
            AdminPage::pageSlugForSection($section) ?? $section,
            function_exists('get_current_user_id') ? get_current_user_id() : 0,
        );
    }

    /**
     * Action callback: a CoreX screen refused the current user. The screen's section key (from
     * AdminPage) and its page slug (from the menu-level gate) name the same screen, so both are
     * recorded under the page slug and counted together.
     */
    public function recordDenied(string $section): void
    {
        $this->record(
            'denied',
            AdminPage::pageSlugForSection($section) ?? $section,
            function_exists('get_current_user_id') ? get_current_user_id() : 0,
        );
    }

    /** A refusal outside the CoreX screens: a Data screen model (spec 112, FR-031). */
    public function refused(int $userId, string $kind, string $section): void
    {
        $this->record($kind, $section, $userId);
    }

    /**
     * @param int|null $at When the refusal happened, as a Unix time; now when omitted.
     */
    public function record(string $kind, string $section, int $userId, ?int $at = null): void
    {
        $at      = $at ?? time();
        $kind    = sanitize_key($kind);
        $section = sanitize_key($section);
        $entries = $this->raw();
        $open    = $this->openEntry($entries, $userId, $kind, $section, $at);

        if ($open !== null && wp_using_ext_object_cache()) {
            $this->countInCache($entries[$open], $at);

            return;
        }

        if ($open !== null) {
            $entries[$open]['count']++;
            $entries[$open]['last'] = $at;
            $this->store($entries, $at);

            return;
        }

        $entries   = $this->settled($entries);
        $entries[] = ['user' => $userId, 'kind' => $kind, 'section' => $section, 'first' => $at, 'last' => $at, 'count' => 1];
        $this->store($entries, $at);

        // A new counted entry, not a repeat inside its hour: one activity event for it, so the
        // Insights security card sees refusals without a write for each (spec 114, US-SAFE-13).
        do_action('corex_access_refusal_counted', $userId, $kind, $section, $at);
    }

    /**
     * The retained entries, newest first by their last refusal, with any count still held in the
     * object cache added in.
     *
     * @param int|null $now Now, as a Unix time, when omitted.
     *
     * @return list<array{user:int,kind:string,section:string,first:int,last:int,count:int}>
     */
    public function entries(?int $now = null): array
    {
        $entries = $this->prune(array_map($this->withCached(...), $this->raw()), $now ?? time());
        usort($entries, static fn (array $a, array $b): int => $b['last'] <=> $a['last']);

        return $entries;
    }

    /**
     * Stores the counts the object cache holds, so a window that nobody closes is not lost when
     * the cache is emptied. Run daily; does nothing without a persistent object cache.
     *
     * @param int|null $now Now, as a Unix time, when omitted.
     */
    public function settle(?int $now = null): void
    {
        if (! wp_using_ext_object_cache()) {
            return;
        }

        $entries = $this->raw();
        $settled = $this->settled($entries);
        if ($settled !== $entries) {
            $this->store($settled, $now ?? time());
        }
    }

    /**
     * The index of this person's entry for this section whose hour has not run out, if any.
     *
     * @param list<array{user:int,kind:string,section:string,first:int,last:int,count:int}> $entries
     */
    private function openEntry(array $entries, int $userId, string $kind, string $section, int $at): ?int
    {
        foreach ($entries as $index => $entry) {
            if ($entry['user'] === $userId && $entry['kind'] === $kind && $entry['section'] === $section
                && $at < $entry['first'] + self::WINDOW_SECONDS) {
                return $index;
            }
        }

        return null;
    }

    /** @param array{user:int,kind:string,section:string,first:int,last:int,count:int} $entry */
    private function countInCache(array $entry, int $at): void
    {
        $cached = $this->cached($entry) ?? ['count' => $entry['count'], 'last' => $entry['last']];

        wp_cache_set($this->cacheKey($entry), ['count' => $cached['count'] + 1, 'last' => $at], self::CACHE_GROUP);
    }

    /**
     * The entries with every count held in the object cache written into them, and those cache
     * entries let go.
     *
     * @param list<array{user:int,kind:string,section:string,first:int,last:int,count:int}> $entries
     *
     * @return list<array{user:int,kind:string,section:string,first:int,last:int,count:int}>
     */
    private function settled(array $entries): array
    {
        return array_map(function (array $entry): array {
            $merged = $this->withCached($entry);
            if ($merged !== $entry) {
                wp_cache_delete($this->cacheKey($entry), self::CACHE_GROUP);
            }

            return $merged;
        }, $entries);
    }

    /**
     * @param array{user:int,kind:string,section:string,first:int,last:int,count:int} $entry
     *
     * @return array{user:int,kind:string,section:string,first:int,last:int,count:int}
     */
    private function withCached(array $entry): array
    {
        $cached = $this->cached($entry);

        return $cached === null ? $entry : ['count' => $cached['count'], 'last' => $cached['last']] + $entry;
    }

    /**
     * @param array{user:int,kind:string,section:string,first:int,last:int,count:int} $entry
     *
     * @return array{count:int,last:int}|null
     */
    private function cached(array $entry): ?array
    {
        $cached = wp_cache_get($this->cacheKey($entry), self::CACHE_GROUP);

        return is_array($cached) && isset($cached['count'], $cached['last'])
            ? ['count' => (int) $cached['count'], 'last' => (int) $cached['last']]
            : null;
    }

    /** @param array{user:int,kind:string,section:string,first:int} $entry */
    private function cacheKey(array $entry): string
    {
        return md5($entry['user'] . '|' . $entry['kind'] . '|' . $entry['section'] . '|' . $entry['first']);
    }

    /**
     * Pruned to 30 days, then each person's oldest beyond {@see MAX_PER_PERSON}, then the log's
     * oldest beyond {@see MAX_ENTRIES}.
     *
     * @param list<array{user:int,kind:string,section:string,first:int,last:int,count:int}> $entries
     */
    private function store(array $entries, int $now): void
    {
        $entries = $this->prune($entries, $now);
        usort($entries, static fn (array $a, array $b): int => $a['last'] <=> $b['last']);

        $perPerson = [];
        foreach (array_reverse($entries) as $entry) {
            $perPerson[$entry['user']][] = $entry;
        }
        $kept = array_merge(...array_map(
            static fn (array $own): array => array_slice($own, 0, self::MAX_PER_PERSON),
            array_values($perPerson) ?: [[]],
        ));
        usort($kept, static fn (array $a, array $b): int => $a['last'] <=> $b['last']);

        update_option(self::OPTION, array_slice($kept, -self::MAX_ENTRIES), false);
    }

    /**
     * The stored entries. One stored before spec 112 (`time` instead of a range) reads as one
     * refusal at that time, and such refusals are folded into one entry by the same hour rule, so
     * the history kept before spec 112 reads as counted too. Anything malformed is skipped.
     *
     * @return list<array{user:int,kind:string,section:string,first:int,last:int,count:int}>
     */
    private function raw(): array
    {
        $stored = get_option(self::OPTION, []);
        if (! is_array($stored)) {
            return [];
        }

        $out = [];
        foreach ($stored as $entry) {
            if (! is_array($entry) || ! isset($entry['kind'])) {
                continue;
            }
            $first   = (int) ($entry['first'] ?? $entry['time'] ?? 0);
            $kind    = (string) $entry['kind'];
            $section = (string) ($entry['section'] ?? '');
            $out[]   = [
                'user'    => (int) ($entry['user'] ?? 0),
                'kind'    => $kind,
                // Before spec 112 a screen was recorded under its section key or its page slug.
                'section' => $kind === 'denied' ? (AdminPage::pageSlugForSection($section) ?? $section) : $section,
                'first'   => $first,
                'last'    => (int) ($entry['last'] ?? $first),
                'count'   => max(1, (int) ($entry['count'] ?? 1)),
            ];
        }

        return $this->folded($out);
    }

    /**
     * Entries for the same person, kind and section that fall within an hour of the first of
     * them, as one.
     *
     * @param list<array{user:int,kind:string,section:string,first:int,last:int,count:int}> $entries
     *
     * @return list<array{user:int,kind:string,section:string,first:int,last:int,count:int}>
     */
    private function folded(array $entries): array
    {
        usort($entries, static fn (array $a, array $b): int => $a['first'] <=> $b['first']);

        $out = [];
        foreach ($entries as $entry) {
            $open = $this->openEntry($out, $entry['user'], $entry['kind'], $entry['section'], $entry['first']);
            if ($open === null) {
                $out[] = $entry;
                continue;
            }

            $out[$open]['count'] += $entry['count'];
            $out[$open]['last']   = max($out[$open]['last'], $entry['last']);
        }

        return $out;
    }

    /**
     * @param list<array{user:int,kind:string,section:string,first:int,last:int,count:int}> $entries
     *
     * @return list<array{user:int,kind:string,section:string,first:int,last:int,count:int}>
     */
    private function prune(array $entries, int $now): array
    {
        $cutoff = $now - (self::RETENTION_DAYS * self::DAY_SECONDS);

        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['last'] >= $cutoff,
        ));
    }
}
