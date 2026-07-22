<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\CaptchaAction;

/**
 * Normalises the per-form protection declaration to a safe, minimal shape.
 *
 * The stored form is deliberately sparse: a key is present only when it says something other
 * than "inherit the global default". An all-inherit declaration normalises to `[]`, so it never
 * touches the version checksum (see {@see FlowConfiguration::checksum()}).
 *
 * Effective resolution — combining these values with the global settings — happens at submission
 * time, not here; this class only cleans what a form declared.
 */
final class FlowProtection
{
    private const CAPTCHA_MODES = ['inherit', 'on', 'off'];

    /**
     * @param mixed $raw The `protection` value from a request document or stored payload.
     * @return array<string,mixed> The clean shape: any of `captcha`, `action`, `threshold`, present only when meaningful.
     */
    public static function normalize(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $clean = [];

        $captcha = is_string($raw['captcha'] ?? null) ? strtolower(trim($raw['captcha'])) : 'inherit';
        if (in_array($captcha, self::CAPTCHA_MODES, true) && $captcha !== 'inherit') {
            $clean['captcha'] = $captcha;
        }

        $action = CaptchaAction::normalise(isset($raw['action']) ? (string) $raw['action'] : null);
        if ($action !== null) {
            $clean['action'] = $action;
        }

        if (isset($raw['threshold']) && is_numeric($raw['threshold'])) {
            $clean['threshold'] = self::clampThreshold((float) $raw['threshold']);
        }

        return $clean;
    }

    /** Constrain a score threshold to the provider-supported range. */
    public static function clampThreshold(float $threshold): float
    {
        return max(0.0, min(1.0, $threshold));
    }
}
