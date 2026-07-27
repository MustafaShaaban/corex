<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

/**
 * What this site has registered, what each thing can do, and what is missing (spec 074, FR-5).
 *
 * The problem it answers: capability was only ever discoverable by walking into it. You found out a
 * model could not be imported into by opening Import and reading a sentence about adapters; you
 * found out a captcha driver had no keys when every form quietly stopped accepting submissions.
 * This gathers those answers into one place, in the words somebody can act on, next to the thing
 * that would fix them.
 *
 * Pure, and deliberately so: the boundary resolves the facts and hands them in, which keeps this
 * unable to invent state and testable without WordPress. It is a *summary* — it never replaces the
 * workflow it describes.
 *
 * Nothing here is allowed to leak a secret. Diagnostics are the sort of thing people screenshot
 * into a support thread, so a fact carrying a credential-shaped key is dropped rather than redacted
 * (a redaction still tells you where to look) and summaries are stripped of paths and traces.
 */
final class CapabilityReport
{
    /** Capability keys, in the order a person would ask about them. */
    private const CAPABILITIES = ['read', 'write', 'import', 'export', 'migrations', 'rollback'];

    /** Which raw source flags decide each capability. Any one of them is enough. */
    private const CAPABILITY_FLAGS = [
        'read'       => ['read'],
        'write'      => ['create', 'update'],
        'import'     => ['import_dry_run'],
        'export'     => ['export_csv', 'export_xlsx'],
        'migrations' => ['migrations'],
        'rollback'   => ['rollback'],
    ];

    private const SECRET_PATTERN = '/(?:password|passphrase|secret|token|api[_-]?key|authorization|cookie|private[_-]?key)/i';

    /**
     * @param array{
     *   forms:list<array<string,mixed>>,
     *   models:list<array<string,mixed>>,
     *   addons:list<array<string,mixed>>,
     *   producers:list<string>,
     *   gaps:list<array<string,mixed>>
     * } $facts
     *
     * @return array<string,mixed>
     */
    public function build(array $facts): array
    {
        $gaps = $this->gaps((array) ($facts['gaps'] ?? []));

        return [
            'forms'     => $this->forms((array) ($facts['forms'] ?? [])),
            'models'    => $this->models((array) ($facts['models'] ?? [])),
            'addons'    => $this->addons((array) ($facts['addons'] ?? [])),
            'producers' => $this->producers((array) ($facts['producers'] ?? [])),
            'gaps'      => $gaps,
            'hasGaps'   => $gaps !== [],
        ];
    }

    /**
     * @param  list<array<string,mixed>> $forms
     * @return array{total:int,items:list<array<string,mixed>>}
     */
    private function forms(array $forms): array
    {
        $items = [];

        foreach ($forms as $form) {
            $items[] = [
                'slug'   => (string) ($form['slug'] ?? ''),
                'label'  => (string) ($form['label'] ?? ''),
                'source' => (string) ($form['source'] ?? ''),
                'active' => (bool) ($form['active'] ?? false),
            ];
        }

        return ['total' => count($items), 'items' => $items];
    }

    /**
     * @param  list<array<string,mixed>> $models
     * @return array{total:int,items:list<array<string,mixed>>,supporting:array<string,int>}
     */
    private function models(array $models): array
    {
        $items      = [];
        $supporting = array_fill_keys(self::CAPABILITIES, 0);

        foreach ($models as $model) {
            $flags  = (array) ($model['capabilities'] ?? []);
            $can    = [];
            $cannot = [];

            foreach (self::CAPABILITIES as $capability) {
                if ($this->supports($flags, $capability)) {
                    $can[] = $capability;
                    $supporting[$capability]++;

                    continue;
                }

                $cannot[] = $capability;
            }

            $items[] = [
                'key'    => (string) ($model['key'] ?? ''),
                'label'  => (string) ($model['label'] ?? ''),
                'can'    => $can,
                'cannot' => $cannot,
            ];
        }

        return ['total' => count($items), 'items' => $items, 'supporting' => $supporting];
    }

    /** @param array<string,mixed> $flags */
    private function supports(array $flags, string $capability): bool
    {
        foreach (self::CAPABILITY_FLAGS[$capability] as $flag) {
            if (($flags[$flag] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string,mixed>> $addons
     * @return array{total:int,active:int,inactive:int,not_installed:int,items:list<array<string,mixed>>}
     */
    private function addons(array $addons): array
    {
        $counts = ['active' => 0, 'inactive' => 0, 'not_installed' => 0];
        $items  = [];

        foreach ($addons as $addon) {
            $state = (string) ($addon['state'] ?? '');

            if (array_key_exists($state, $counts)) {
                $counts[$state]++;
            }

            $items[] = [
                'slug'  => (string) ($addon['slug'] ?? ''),
                'label' => (string) ($addon['label'] ?? ''),
                'state' => $state,
            ];
        }

        return ['total' => count($items), ...$counts, 'items' => $items];
    }

    /**
     * @param  list<string> $producers
     * @return array{total:int,keys:list<string>}
     */
    private function producers(array $producers): array
    {
        $keys = array_values(array_filter(array_map('strval', $producers), static fn (string $k): bool => $k !== ''));

        return ['total' => count($keys), 'keys' => $keys];
    }

    /**
     * @param  list<array<string,mixed>> $gaps
     * @return list<array<string,mixed>>
     */
    private function gaps(array $gaps): array
    {
        $clean = [];

        foreach ($gaps as $gap) {
            if (! is_array($gap) || $this->carriesSecret($gap)) {
                continue;
            }

            $key = (string) ($gap['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $clean[] = [
                'key'          => $key,
                'summary'      => self::readable((string) ($gap['summary'] ?? '')),
                'action_label' => (string) ($gap['action_label'] ?? ''),
                'action_url'   => (string) ($gap['action_url'] ?? ''),
            ];
        }

        return $clean;
    }

    /** @param array<mixed> $values */
    private function carriesSecret(array $values): bool
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_PATTERN, $key) === 1) {
                return true;
            }

            if (is_array($value) && $this->carriesSecret($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A summary a person can read: one line, no file paths, no stack frames.
     *
     * A raw exception message is the usual source of both, and it is exactly what tends to get
     * pasted into a screenshot — so the shape is removed here rather than trusted not to appear.
     */
    private static function readable(string $summary): string
    {
        $summary = (string) preg_replace('/#\d+\s+.*$/m', '', $summary);
        $summary = (string) preg_replace('/\S*\.php(?::\d+)?/', '', $summary);
        $summary = (string) preg_replace('/[A-Za-z]:\\\\\S+|\/(?:var|home|usr|srv)\/\S+/', '', $summary);
        $summary = (string) preg_replace('/\s+/', ' ', $summary);

        return trim($summary);
    }
}
