<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Config\Readiness\HardeningFactSource;
use Corex\Config\Readiness\SiteChecks;
use Corex\Config\Readiness\SiteReadinessFacts;
use Corex\Config\Security\HardeningChecks;
use Corex\Events\EventDispatcher;
use DateTimeImmutable;

/**
 * The one readiness evaluation (spec 118): the launch checklist that the Overview, Operations &
 * Security, the readiness notifications, the Dashboard widget and the Setup Wizard all read, and
 * the evidence a Production launch is gated on.
 *
 * Two groups. "Security and hosting" holds the hardening checks: a failing one blocks Production,
 * and when the facts cannot be read each one is "Could not check", which does not block. "Site and
 * content" holds {@see SiteChecks}, which advise and never block (OVR-D4). Without the site facts
 * (a caller that builds the factory by hand) the checklist is the hardening group alone, as it was.
 */
final class ProductionReadinessSnapshotFactory
{
    /** Where each hardening check is fixed, as an admin path. */
    private const HARDENING_FIXES = [
        'ssl'             => 'options-general.php',
        'file_edit'       => 'admin.php?page=corex-operations-security&tab=hardening',
        'debug_display'   => 'admin.php?page=corex-operations-security&tab=hardening',
        'default_admin'   => 'users.php?s=admin',
        'search_indexing' => 'options-reading.php',
        'auth_salts'      => 'admin.php?page=corex-operations-security&tab=hardening',
    ];

    /** The hardening facts all set, which only names the checks when the real facts cannot be read. */
    private const NAMING_FACTS = [
        'ssl'                 => true,
        'fileEditDisabled'    => true,
        'debugDisplayOff'     => true,
        'defaultAdminAbsent'  => true,
        'indexingAllowed'     => true,
        'authSaltsConfigured' => true,
    ];

    public function __construct(
        private readonly HardeningChecks $hardening,
        private readonly ?EventDispatcher $events = null,
        private readonly HardeningFactSource $hardeningFacts = new HardeningFactSource(),
        private readonly ?SiteReadinessFacts $siteFacts = null,
        private readonly SiteChecks $siteChecks = new SiteChecks(),
    ) {
    }

    public function fromCurrentSite(DateTimeImmutable $now): ReadinessSnapshot
    {
        $checkedAt = $now->format(DATE_ATOM);
        $checks    = [...$this->hardeningChecks(), ...$this->siteChecks($now)];

        $snapshot = new ReadinessSnapshot(array_map(
            static fn (array $check): array => [
                ...$check,
                'resolution_url' => admin_url($check['resolution_url']),
                'checked_at'     => $checkedAt,
                'evidence_hash'  => hash('sha256', json_encode([
                    'key'     => $check['key'],
                    'state'   => $check['state'],
                    'summary' => $check['summary'],
                ], JSON_THROW_ON_ERROR)),
            ],
            $checks,
        ));

        // Announce the evaluation so the Notification Center can reconcile readiness conditions. The
        // checks are local, so this is safe on the render path (FR-015).
        $this->events?->dispatch(new ReadinessEvaluatedEvent($snapshot));

        return $snapshot;
    }

    /**
     * @return list<array{key:string,group:string,label:string,state:string,summary:string,resolution_url:string}>
     */
    private function hardeningChecks(): array
    {
        try {
            $facts    = $this->hardeningFacts->gather();
            $readable = true;
        } catch (\Throwable) {
            $facts    = self::NAMING_FACTS;
            $readable = false;
        }

        return array_map(
            static fn (array $check): array => [
                'key'            => $check['key'],
                'group'          => ReadinessSnapshot::GROUP_SECURITY,
                'label'          => $check['label'],
                'state'          => match (true) {
                    ! $readable                               => ReadinessSnapshot::UNAVAILABLE,
                    $check['status'] === HardeningChecks::PASS => ReadinessSnapshot::PASS,
                    default                                    => ReadinessSnapshot::BLOCKING,
                },
                'summary'        => $readable ? $check['detail'] : __('This could not be checked.', 'corex'),
                'resolution_url' => self::HARDENING_FIXES[$check['key']] ?? 'admin.php?page=corex-operations-security&tab=hardening',
            ],
            $this->hardening->checks($facts),
        );
    }

    /**
     * @return list<array{key:string,group:string,label:string,state:string,summary:string,resolution_url:string}>
     */
    private function siteChecks(DateTimeImmutable $now): array
    {
        if ($this->siteFacts === null) {
            return [];
        }

        return array_map(
            static fn (array $check): array => ['group' => ReadinessSnapshot::GROUP_SITE] + $check,
            $this->siteChecks->checks($this->siteFacts->gather($now)),
        );
    }
}
