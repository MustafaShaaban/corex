<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

use Corex\Health\HealthStatus;

/**
 * The pure scoring vocabulary shared by every insight provider: a 0–100 score maps to a letter
 * grade (A–F) and to the same good/recommended/critical status the rest of Corex uses (so the
 * dashboard, the health screen, and the CLI all speak one language).
 */
final class Grade
{
    public static function clamp(int $score): int
    {
        return max(0, min(100, $score));
    }

    public static function letter(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 50 => 'D',
            default      => 'F',
        };
    }

    public static function status(int $score): HealthStatus
    {
        return match (true) {
            $score >= 90 => HealthStatus::Good,
            $score >= 50 => HealthStatus::Recommended,
            default      => HealthStatus::Critical,
        };
    }
}
