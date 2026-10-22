<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

use Corex\Health\HealthStatus;

/**
 * One provider's result: a 0–100 score (derived grade + status), a one-line summary, a list of
 * metrics ({label, value, unit}), and a list of plain-language recommendations. Immutable and
 * self-describing — the same shape feeds the REST response, the cache, and the admin card, and
 * never carries a secret.
 *
 * A run that could not complete is a failed result (spec 114, US-SAFE-12): no score, no grade,
 * the failure's kind and the reason as its summary. It was stored as a score of 50, which drew a
 * D for a site nobody had measured.
 */
final class InsightResult
{
    public const STATUS_FAILED = 'failed';

    public readonly ?int $score;
    public readonly ?string $grade;
    public readonly ?HealthStatus $status;

    /**
     * @param int|null                                             $score   null only for a failed run
     * @param list<array{label:string,value:string,unit?:string}> $metrics
     * @param list<string>                                         $recommendations
     * @param string                                               $failure the failure's kind; '' for a completed run
     */
    public function __construct(
        public readonly string $providerId,
        public readonly string $label,
        ?int $score,
        public readonly string $summary,
        public readonly array $metrics = [],
        public readonly array $recommendations = [],
        public readonly int $checkedAt = 0,
        public readonly string $failure = '',
    ) {
        $this->score  = $failure === '' && $score !== null ? Grade::clamp($score) : null;
        $this->grade  = $this->score === null ? null : Grade::letter($this->score);
        $this->status = $this->score === null ? null : Grade::status($this->score);
    }

    /** A run that could not complete, and why, in words. */
    public static function failed(string $providerId, string $label, string $failure, string $reason, int $checkedAt): self
    {
        return new self($providerId, $label, null, $reason, [], [], $checkedAt, $failure);
    }

    public function isFailed(): bool
    {
        return $this->failure !== '';
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $row = [
            'provider'        => $this->providerId,
            'label'           => $this->label,
            'score'           => $this->score,
            'grade'           => $this->grade,
            'status'          => $this->isFailed() ? self::STATUS_FAILED : $this->status?->value,
            'summary'         => $this->summary,
            'metrics'         => $this->metrics,
            'recommendations' => $this->recommendations,
            'checkedAt'       => $this->checkedAt,
        ];

        return $this->isFailed() ? $row + ['failure' => $this->failure] : $row;
    }
}
