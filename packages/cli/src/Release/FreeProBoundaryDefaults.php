<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Default Free/Core and Pro candidate capability boundaries for spec 055.
 */
final class FreeProBoundaryDefaults
{
    /**
     * @return list<string>
     */
    public static function requiredFreeCoreCapabilities(): array
    {
        return array_column(self::freeCoreItems(), 'capability');
    }

    public static function matrix(): FreeProBoundaryMatrix
    {
        return FreeProBoundaryMatrix::fromItems(array_map(
            static fn (array $item): FreeProBoundaryItem => FreeProBoundaryItem::fromArray($item),
            self::items(),
        ));
    }

    /**
     * @return list<array{capability:string,classification:string,reason:string,securityCritical:bool}>
     */
    private static function items(): array
    {
        return [
            ...self::freeCoreItems(),
            ...self::proCandidateItems(),
        ];
    }

    /**
     * @return list<array{capability:string,classification:string,reason:string,securityCritical:bool}>
     */
    private static function freeCoreItems(): array
    {
        return [
            self::freeCore('core framework', 'Adoption baseline; the runtime framework cannot be commercial-only.', true),
            self::freeCore('basic blocks and DLS', 'First company sites need the native DLS and basic Corex blocks.', false),
            self::freeCore('basic forms and contact form', 'Contact is a baseline trust and lead-capture workflow.', true),
            self::freeCore('basic config and options', 'Site setup and safe configuration must be available in Core.', true),
            self::freeCore('basic media fields', 'Native media handling is required for company identity pages.', false),
            self::freeCore('basic captcha and honeypot', 'Spam protection is a security baseline, not a paid add-on.', true),
            self::freeCore('accessibility', 'WCAG-oriented output is a trust baseline.', true),
            self::freeCore('RTL', 'Arabic and logical-layout support are core project requirements.', true),
            self::freeCore('i18n', 'Translation-ready strings are required for all shippable Corex work.', true),
            self::freeCore('basic make:site', 'Client-site scaffolding is needed for adoption and safe separation.', false),
            self::freeCore('basic docs and deployment docs', 'Basic operating docs are required for safe adoption.', false),
        ];
    }

    /**
     * @return list<array{capability:string,classification:string,reason:string,securityCritical:bool}>
     */
    private static function proCandidateItems(): array
    {
        return [
            self::proCandidate('advanced newsletter', 'Segmentation, automation, and campaign tooling are advanced commercial scope.'),
            self::proCandidate('bookings', 'Scheduling workflows are vertical and not required for basic company sites.'),
            self::proCandidate('careers and ATS', 'Applicant tracking and pipeline automation are vertical commercial scope.'),
            self::proCandidate('WooCommerce kit', 'Storefront integration is optional and dependency-gated.'),
            self::proCandidate('advanced email providers', 'Provider routing, queues, logs, and templates are advanced email operations.'),
            self::proCandidate('advanced media CDN and optimization', 'CDN/offload/image-pipeline automation is infrastructure-specific.'),
            self::proCandidate('Data Manager Pro', 'Advanced data management is commercial admin tooling.'),
            self::proCandidate('white-label admin', 'Agency branding and reseller surfaces are commercial positioning.'),
            self::proCandidate('starter kits', 'Packaged vertical starters can be commercial without blocking Core adoption.'),
            self::proCandidate('Azure and DevOps automation', 'Cloud automation is deployment-specific advanced scope.'),
            self::proCandidate('AI-agent governance dashboards', 'Governance dashboards are advanced operational reporting.'),
            self::proCandidate('multi-company identity kit', 'Managing many company identities is advanced agency scope.'),
            self::proCandidate('client portal dashboard', 'Client portals are application scope beyond first company sites.'),
        ];
    }

    /**
     * @return array{capability:string,classification:string,reason:string,securityCritical:bool}
     */
    private static function freeCore(string $capability, string $reason, bool $securityCritical): array
    {
        return [
            'capability' => $capability,
            'classification' => FreeProBoundaryItem::CLASSIFICATION_FREE_CORE,
            'reason' => $reason,
            'securityCritical' => $securityCritical,
        ];
    }

    /**
     * @return array{capability:string,classification:string,reason:string,securityCritical:bool}
     */
    private static function proCandidate(string $capability, string $reason): array
    {
        return [
            'capability' => $capability,
            'classification' => FreeProBoundaryItem::CLASSIFICATION_PRO_CANDIDATE,
            'reason' => $reason,
            'securityCritical' => false,
        ];
    }
}
