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
 */
final class InsightResult
{
    public readonly int $score;
    public readonly string $grade;
    public readonly HealthStatus $status;

    /**
     * @param list<array{label:string,value:string,unit?:string}> $metrics
     * @param list<string>                                         $recommendations
     */
    public function __construct(
        public readonly string $providerId,
        public readonly string $label,
        int $score,
        public readonly string $summary,
        public readonly array $metrics = [],
        public readonly array $recommendations = [],
        public readonly int $checkedAt = 0,
    ) {
        $this->score  = Grade::clamp($score);
        $this->grade  = Grade::letter($this->score);
        $this->status = Grade::status($this->score);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'provider'        => $this->providerId,
            'label'           => $this->label,
            'score'           => $this->score,
            'grade'           => $this->grade,
            'status'          => $this->status->value,
            'summary'         => $this->summary,
            'metrics'         => $this->metrics,
            'recommendations' => $this->recommendations,
            'checkedAt'       => $this->checkedAt,
        ];
    }
}
