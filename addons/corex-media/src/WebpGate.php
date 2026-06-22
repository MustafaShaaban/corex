<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

/**
 * The WebP activation gate (spec 062): decides whether a generated WebP derivative may be served, so a
 * WebP is never delivered just because it exists. Pure — given pre-measured facts about the original and
 * the derivative it returns `active_for_delivery` + an `inactive_reason` + the size saving. The WP boundary
 * ({@see WebpMeta::measure()}) supplies the facts (filesize/getimagesize); this class is the judgement.
 *
 * Gate (all must hold to activate): the derivative is present + valid, its dimensions match the original,
 * and it is smaller than the original by at least the configured threshold (default 5%).
 */
final class WebpGate
{
    /**
     * @param array{generated_valid:bool,original_bytes:int,generated_bytes:int,original_dimensions:string,generated_dimensions:string} $facts
     *
     * @return array{active:bool,reason:string,saving:float}
     */
    public static function evaluate(array $facts, float $minSaving = MediaSettings::DEFAULT_MIN_SAVING): array
    {
        $originalBytes  = (int) $facts['original_bytes'];
        $generatedBytes = (int) $facts['generated_bytes'];

        if (empty($facts['generated_valid']) || $generatedBytes <= 0) {
            return self::inactive('generated-missing-or-invalid');
        }
        if ($originalBytes <= 0) {
            return self::inactive('original-size-unknown');
        }
        if ((string) $facts['original_dimensions'] !== (string) $facts['generated_dimensions']) {
            return self::inactive('dimensions-mismatch');
        }

        $saving = (1 - $generatedBytes / $originalBytes) * 100;

        if ($saving < $minSaving) {
            return [
                'active' => false,
                'reason' => sprintf('saving-%.1f%%-below-threshold-%.1f%%', $saving, $minSaving),
                'saving' => round($saving, 2),
            ];
        }

        return ['active' => true, 'reason' => '', 'saving' => round($saving, 2)];
    }

    /**
     * @return array{active:bool,reason:string,saving:float}
     */
    private static function inactive(string $reason): array
    {
        return ['active' => false, 'reason' => $reason, 'saving' => 0.0];
    }
}
