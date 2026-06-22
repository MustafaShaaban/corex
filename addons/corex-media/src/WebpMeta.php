<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

/**
 * The tracked record for one CoreX-generated WebP derivative (spec 062): the original/generated paths +
 * bytes, the size saving, dimensions, quality, a source hash, when it was generated, and the gate result
 * (`active_for_delivery` + `inactive_reason`). Persisted in attachment meta (`_corex_webp`) so delivery can
 * consult the gate and the reset command knows exactly which files are CoreX-generated (and never touches
 * originals or untracked WebP). `measure()` reads the files (filesize/getimagesize/md5) and applies the gate.
 */
final class WebpMeta
{
    public const META_KEY = '_corex_webp';

    public function __construct(
        public readonly string $originalPath,
        public readonly string $generatedPath,
        public readonly int $originalBytes,
        public readonly int $generatedBytes,
        public readonly float $saving,
        public readonly string $dimensions,
        public readonly int $quality,
        public readonly string $sourceHash,
        public readonly string $generatedAt,
        public readonly bool $activeForDelivery,
        public readonly string $inactiveReason,
    ) {
    }

    /**
     * Measure a freshly-generated derivative against its original and apply the {@see WebpGate}. The
     * "generated_at" is injected so the result is deterministic in tests.
     */
    public static function measure(string $originalPath, string $generatedPath, int $quality, float $minSaving, ?string $generatedAt = null): self
    {
        $originalBytes  = is_file($originalPath) ? (int) filesize($originalPath) : 0;
        $generatedExists = is_file($generatedPath);
        $generatedBytes = $generatedExists ? (int) filesize($generatedPath) : 0;

        $originalDims  = self::dimensions($originalPath);
        $generatedDims = self::dimensions($generatedPath);

        $gate = WebpGate::evaluate([
            'generated_valid'      => $generatedExists && $generatedDims !== '',
            'original_bytes'       => $originalBytes,
            'generated_bytes'      => $generatedBytes,
            'original_dimensions'  => $originalDims,
            'generated_dimensions' => $generatedDims,
        ], $minSaving);

        return new self(
            $originalPath,
            $generatedPath,
            $originalBytes,
            $generatedBytes,
            (float) $gate['saving'],
            $originalDims,
            $quality,
            is_file($originalPath) ? (string) md5_file($originalPath) : '',
            $generatedAt ?? gmdate('c'),
            (bool) $gate['active'],
            (string) $gate['reason'],
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'original_path'      => $this->originalPath,
            'generated_path'     => $this->generatedPath,
            'original_bytes'     => $this->originalBytes,
            'generated_bytes'    => $this->generatedBytes,
            'saving'             => $this->saving,
            'dimensions'         => $this->dimensions,
            'quality'            => $this->quality,
            'source_hash'        => $this->sourceHash,
            'generated_at'       => $this->generatedAt,
            'active_for_delivery' => $this->activeForDelivery,
            'inactive_reason'    => $this->inactiveReason,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['original_path'] ?? ''),
            (string) ($data['generated_path'] ?? ''),
            (int) ($data['original_bytes'] ?? 0),
            (int) ($data['generated_bytes'] ?? 0),
            (float) ($data['saving'] ?? 0),
            (string) ($data['dimensions'] ?? ''),
            (int) ($data['quality'] ?? 0),
            (string) ($data['source_hash'] ?? ''),
            (string) ($data['generated_at'] ?? ''),
            (bool) ($data['active_for_delivery'] ?? false),
            (string) ($data['inactive_reason'] ?? ''),
        );
    }

    /** "WxH" for an image file, or '' when it can't be read. */
    private static function dimensions(string $path): string
    {
        if (! is_file($path)) {
            return '';
        }

        $size = @getimagesize($path);

        return is_array($size) && isset($size[0], $size[1]) ? $size[0] . 'x' . $size[1] : '';
    }
}
