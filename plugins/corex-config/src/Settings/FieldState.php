<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Support\Config\SettingLock;

/**
 * How one Settings field is drawn (spec 119): what its control shows, the stored value Discard
 * puts back, where its value is locked when it is, and whether a secret has a stored value.
 */
final readonly class FieldState
{
    public function __construct(
        public string $shown,
        public string $saved,
        public ?SettingLock $lock = null,
        public bool $secretSaved = false,
    ) {
    }

    /** A field that shows what is stored, as an option page draws every field. */
    public static function plain(string $value): self
    {
        return new self($value, $value);
    }

    public function locked(): bool
    {
        return $this->lock !== null;
    }

    /** The same field showing what was typed into it and refused, so it can be put right. */
    public function asTyped(string $typed): self
    {
        return new self($typed, $this->saved, $this->lock, $this->secretSaved);
    }
}
