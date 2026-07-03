<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

/**
 * The access audit log (spec 067, design: "Corex Access & Abilities" → Audit log). Records REAL
 * access events only — currently permission-denied attempts on CoreX screens, published through the
 * `corex_admin_access_denied` action by {@see \Corex\Admin\AdminPage::permissionDenied()} and
 * {@see AccessDeniedGate}. CoreX never mutates roles or capabilities, so no grant/revoke entries can
 * exist yet; the screen states that honestly instead of inventing history. Entries live in a bounded,
 * autoload-off option and are pruned to the designed 30-day window on every read and write.
 */
final class AccessAuditLog
{
    private const OPTION         = 'corex_access_audit_log';
    private const MAX_ENTRIES    = 100;
    private const RETENTION_DAYS = 30;
    private const DAY_SECONDS    = 86400;

    public function register(): void
    {
        add_action('corex_admin_access_denied', [$this, 'recordDenied']);
    }

    /**
     * Action callback: a CoreX screen refused the current user. `$section` is the screen's section
     * key (from AdminPage) or its page slug (from the menu-level gate).
     */
    public function recordDenied(string $section): void
    {
        $this->record('denied', $section, function_exists('get_current_user_id') ? get_current_user_id() : 0);
    }

    public function record(string $kind, string $section, int $userId): void
    {
        $entries   = $this->prune($this->raw());
        $entries[] = [
            'time'    => time(),
            'user'    => $userId,
            'kind'    => sanitize_key($kind),
            'section' => sanitize_key($section),
        ];

        if (count($entries) > self::MAX_ENTRIES) {
            $entries = array_slice($entries, -self::MAX_ENTRIES);
        }

        update_option(self::OPTION, $entries, false);
    }

    /**
     * The retained entries, newest first, pruned to the 30-day window.
     *
     * @return list<array{time:int,user:int,kind:string,section:string}>
     */
    public function entries(): array
    {
        return array_reverse($this->prune($this->raw()));
    }

    /**
     * @return list<array{time:int,user:int,kind:string,section:string}>
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
            $out[] = [
                'time'    => (int) ($entry['time'] ?? 0),
                'user'    => (int) ($entry['user'] ?? 0),
                'kind'    => (string) $entry['kind'],
                'section' => (string) ($entry['section'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{time:int,user:int,kind:string,section:string}> $entries
     *
     * @return list<array{time:int,user:int,kind:string,section:string}>
     */
    private function prune(array $entries): array
    {
        $cutoff = time() - (self::RETENTION_DAYS * self::DAY_SECONDS);

        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['time'] >= $cutoff,
        ));
    }
}
