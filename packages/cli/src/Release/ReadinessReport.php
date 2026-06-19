<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Collects spec 055 readiness findings and reports category completeness.
 */
final class ReadinessReport
{
    /**
     * @var list<string>
     */
    private const REQUIRED_CATEGORIES = [
        'runtime-gating',
        'metadata',
        'ci-security',
        'make-site',
        'deployment',
        'component-coverage',
        'free-pro',
        'multi-agent',
    ];

    /**
     * @var array<string,int>
     */
    private const STATUS_PRIORITY = [
        ReadinessFinding::STATUS_FAIL => 5,
        ReadinessFinding::STATUS_WARNING => 4,
        ReadinessFinding::STATUS_ENVIRONMENT_GATED => 3,
        ReadinessFinding::STATUS_NOT_RUN => 2,
        ReadinessFinding::STATUS_PASS => 1,
    ];

    /**
     * @param list<ReadinessFinding> $findings
     */
    private function __construct(private readonly array $findings)
    {
    }

    /**
     * @param list<ReadinessFinding> $findings
     */
    public static function fromFindings(array $findings): self
    {
        return new self($findings);
    }

    public function isComplete(): bool
    {
        return $this->missingCategories() === [];
    }

    /**
     * @return list<string>
     */
    public function missingCategories(): array
    {
        $present = array_fill_keys(array_map(
            static fn (ReadinessFinding $finding): string => $finding->category,
            $this->findings,
        ), true);

        return array_values(array_filter(
            self::REQUIRED_CATEGORIES,
            static fn (string $category): bool => ! isset($present[$category]),
        ));
    }

    public function status(): string
    {
        $status = ReadinessFinding::STATUS_PASS;
        $priority = self::STATUS_PRIORITY[$status];

        foreach ($this->findings as $finding) {
            $findingPriority = self::STATUS_PRIORITY[$finding->status];

            if ($findingPriority > $priority) {
                $status = $finding->status;
                $priority = $findingPriority;
            }
        }

        return $status;
    }
}
