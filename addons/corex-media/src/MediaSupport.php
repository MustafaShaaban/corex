<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

/**
 * A human-readable server-support summary for the Media settings panel (spec 061): GD / Imagick
 * presence, WebP encode support, and whether the uploads directory is writable. The capability is
 * injected (pure to format); the uploads-writability check reads the runtime via an injectable
 * callable so it can be unit-tested without WordPress.
 */
final class MediaSupport
{
    /** @var callable():bool */
    private $uploadsWritable;

    /**
     * @param (callable():bool)|null $uploadsWritable defaults to a real wp_get_upload_dir() probe
     */
    public function __construct(private readonly ImageCapability $capability, ?callable $uploadsWritable = null)
    {
        $this->uploadsWritable = $uploadsWritable ?? static function (): bool {
            $dir = function_exists('wp_get_upload_dir') ? wp_get_upload_dir() : [];
            $base = (string) ($dir['basedir'] ?? '');

            return $base !== '' && is_writable($base);
        };
    }

    public function uploadsWritable(): bool
    {
        return (bool) ($this->uploadsWritable)();
    }

    /** A one-line, translatable status string: "GD: yes · Imagick: no · WebP encode: yes · Uploads writable: yes". */
    public function summary(): string
    {
        $yes = __('yes', 'corex');
        $no  = __('no', 'corex');

        return sprintf(
            /* translators: each %s is "yes" or "no". */
            __('GD: %1$s · Imagick: %2$s · WebP encode: %3$s · Uploads writable: %4$s', 'corex'),
            $this->capability->gd ? $yes : $no,
            $this->capability->imagick ? $yes : $no,
            $this->capability->canWebp() ? $yes : $no,
            $this->uploadsWritable() ? $yes : $no,
        );
    }
}
