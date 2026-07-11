<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit\Setup;

defined('ABSPATH') || exit;

/**
 * Pure launch-readiness checklist for the setup wizard's final step (spec 068 FR-142). It turns
 * already-gathered, real facts into the approved checklist covering search indexing, debug output,
 * environment, transactional email, captcha/security hardening, legal pages, Forms testing, and
 * performance/readiness. Each item is a truthful pass / warning / blocker — never a fabricated
 * green. A launch is unsafe (FR-134) while any blocker remains. WordPress-free, so it is testable.
 */
final class LaunchChecklist
{
    public const PASS    = 'pass';
    public const WARNING = 'warning';
    public const BLOCKER = 'blocker';

    /**
     * @param array{
     *   searchIndexable:bool,
     *   debugDisplayOff:bool,
     *   isProduction:bool,
     *   mailConfigured:bool,
     *   captchaConfigured:bool,
     *   hardeningWarnings:int,
     *   legalPagesPresent:bool,
     *   formsTested:bool,
     *   readinessRun:bool
     * } $facts
     *
     * @return array{items:list<array{key:string,label:string,status:string,note:string}>,blockers:int,warnings:int,ready:bool}
     */
    public function build(array $facts): array
    {
        $items = [
            $this->item(
                'indexing',
                __('Search engine indexing', 'corex'),
                $facts['searchIndexable'] ? self::PASS : self::BLOCKER,
                $facts['searchIndexable']
                    ? __('Search engines can index this site.', 'corex')
                    : __('Indexing is discouraged (Settings → Reading) — a launched site must be indexable.', 'corex'),
            ),
            $this->item(
                'debug',
                __('Debug output hidden', 'corex'),
                $facts['debugDisplayOff'] ? self::PASS : self::BLOCKER,
                $facts['debugDisplayOff']
                    ? __('Errors are not shown to visitors.', 'corex')
                    : __('WP_DEBUG_DISPLAY is on — errors would leak to visitors.', 'corex'),
            ),
            $this->item(
                'environment',
                __('Production environment', 'corex'),
                $facts['isProduction'] ? self::PASS : self::WARNING,
                $facts['isProduction']
                    ? __('The environment type is production.', 'corex')
                    : __('The environment is not set to production.', 'corex'),
            ),
            $this->item(
                'email',
                __('Transactional email', 'corex'),
                $facts['mailConfigured'] ? self::PASS : self::WARNING,
                $facts['mailConfigured']
                    ? __('A from address is configured.', 'corex')
                    : __('No transactional email from address is configured yet.', 'corex'),
            ),
            $this->item(
                'security',
                __('Captcha & security hardening', 'corex'),
                $this->securityStatus($facts),
                $this->securityNote($facts),
            ),
            $this->item(
                'legal',
                __('Legal pages', 'corex'),
                $facts['legalPagesPresent'] ? self::PASS : self::WARNING,
                $facts['legalPagesPresent']
                    ? __('Privacy, terms, and cookie pages are present.', 'corex')
                    : __('One or more legal pages are missing.', 'corex'),
            ),
            $this->item(
                'forms',
                __('Forms tested', 'corex'),
                $facts['formsTested'] ? self::PASS : self::WARNING,
                $facts['formsTested']
                    ? __('At least one flow has a recorded test run.', 'corex')
                    : __('No flow has a recorded test run yet.', 'corex'),
            ),
            $this->item(
                'performance',
                __('Performance & readiness', 'corex'),
                $facts['readinessRun'] ? self::PASS : self::WARNING,
                $facts['readinessRun']
                    ? __('Performance/readiness checks have been run.', 'corex')
                    : __('Run the Insights performance and readiness checks.', 'corex'),
            ),
        ];

        $blockers = count(array_filter($items, static fn (array $i): bool => $i['status'] === self::BLOCKER));
        $warnings = count(array_filter($items, static fn (array $i): bool => $i['status'] === self::WARNING));

        return [
            'items'    => $items,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'ready'    => $blockers === 0,
        ];
    }

    /**
     * @return array{key:string,label:string,status:string,note:string}
     */
    private function item(string $key, string $label, string $status, string $note): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'note' => $note];
    }

    /**
     * @param array<string,mixed> $facts
     */
    private function securityStatus(array $facts): string
    {
        if (! $facts['captchaConfigured']) {
            return self::WARNING;
        }

        return $facts['hardeningWarnings'] === 0 ? self::PASS : self::WARNING;
    }

    /**
     * @param array<string,mixed> $facts
     */
    private function securityNote(array $facts): string
    {
        if (! $facts['captchaConfigured']) {
            return __('Captcha is not configured — spam protection is recommended before launch.', 'corex');
        }

        if ($facts['hardeningWarnings'] > 0) {
            return sprintf(
                /* translators: %d: number of hardening checks needing attention */
                _n('%d security hardening check needs attention.', '%d security hardening checks need attention.', (int) $facts['hardeningWarnings'], 'corex'),
                (int) $facts['hardeningWarnings'],
            );
        }

        return __('Captcha is configured and hardening checks pass.', 'corex');
    }
}
