<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Immutable readiness evidence used before sensitive operations such as Production launch: the one
 * launch checklist every screen reads (spec 118). Each check belongs to a group, `security`
 * (Security and hosting, the hardening checks, the only ones that can block) or `site` (Site and
 * content, which advise). A check that does not apply to this site is `not_applicable` and is left
 * out of every count.
 */
final readonly class ReadinessSnapshot
{
    public const PASS           = 'pass';
    public const WARNING        = 'warning';
    public const BLOCKING       = 'blocking';
    public const UNAVAILABLE    = 'unavailable';
    public const NOT_APPLICABLE = 'not_applicable';

    public const GROUP_SECURITY = 'security';
    public const GROUP_SITE     = 'site';

    private const STATES = [self::PASS, self::WARNING, self::BLOCKING, self::UNAVAILABLE, self::NOT_APPLICABLE];

    private const GROUPS = [self::GROUP_SECURITY, self::GROUP_SITE];

    /** @var list<array{key:string,group:string,label:string,state:string,summary:string,resolution_url:string,checked_at:string,evidence_hash:string}> */
    private array $checks;

    /**
     * @param list<array{key:string,group:string,label:string,state:string,summary:string,resolution_url:string,checked_at:string,evidence_hash:string}> $checks
     */
    public function __construct(array $checks)
    {
        if ($checks === []) {
            throw new InvalidArgumentException('Readiness snapshot requires at least one check.');
        }

        $this->checks = array_values(array_map([$this, 'validateCheck'], $checks));
    }

    /** @return list<array{key:string,group:string,label:string,state:string,summary:string,resolution_url:string,checked_at:string,evidence_hash:string}> */
    public function checks(): array
    {
        return $this->checks;
    }

    public function hasBlockingChecks(): bool
    {
        return $this->blockingChecks() !== [];
    }

    /** @return list<string> */
    public function blockingKeys(): array
    {
        return array_map(
            static fn (array $check): string => $check['key'],
            $this->blockingChecks(),
        );
    }

    public function targetHash(): string
    {
        $checks = $this->checks;
        usort($checks, static fn (array $left, array $right): int => $left['key'] <=> $right['key']);

        return hash('sha256', json_encode($checks, JSON_THROW_ON_ERROR));
    }

    /** @return list<array{key:string,group:string,label:string,state:string,summary:string,resolution_url:string,checked_at:string,evidence_hash:string}> */
    private function blockingChecks(): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (array $check): bool => $check['state'] === self::BLOCKING,
        ));
    }

    /**
     * @param array<string,mixed> $check
     *
     * @return array{key:string,group:string,label:string,state:string,summary:string,resolution_url:string,checked_at:string,evidence_hash:string}
     */
    private function validateCheck(array $check): array
    {
        $key = (string) ($check['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $key) !== 1) {
            throw new InvalidArgumentException('Readiness check key is invalid.');
        }

        $state = (string) ($check['state'] ?? '');
        if (! in_array($state, self::STATES, true)) {
            throw new InvalidArgumentException('Readiness check state is invalid.');
        }

        $evidenceHash = (string) ($check['evidence_hash'] ?? '');
        if (preg_match('/^[0-9a-f]{64}$/', $evidenceHash) !== 1) {
            throw new InvalidArgumentException('Readiness evidence hash must be SHA-256.');
        }

        $group = (string) ($check['group'] ?? self::GROUP_SECURITY);
        if (! in_array($group, self::GROUPS, true)) {
            throw new InvalidArgumentException('Readiness check group is invalid.');
        }

        return [
            'key'            => $key,
            'group'          => $group,
            'label'          => (string) ($check['label'] ?? ''),
            'state'          => $state,
            'summary'        => (string) ($check['summary'] ?? ''),
            'resolution_url' => (string) ($check['resolution_url'] ?? ''),
            'checked_at'     => (string) ($check['checked_at'] ?? ''),
            'evidence_hash'  => $evidenceHash,
        ];
    }
}
