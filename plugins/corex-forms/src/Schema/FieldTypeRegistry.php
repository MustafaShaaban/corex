<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Schema;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Ordered field-type extension registry used by the builder and schema validator.
 */
final class FieldTypeRegistry
{
    /** @var array<string,array{key:string,label:string,settings:array<string,bool>,built_in:bool}> */
    private array $types = [];

    public function __construct()
    {
        foreach ($this->builtIns() as $key => $settings) {
            $this->types[$key] = $this->definition($key, $key, $settings, true);
        }
    }

    /** @param array<string,bool> $settings */
    public function register(string $key, string $label, array $settings = []): void
    {
        if (! preg_match('/^[a-z][a-z0-9-]*$/', $key) || isset($this->types[$key])) {
            throw new InvalidArgumentException(sprintf('Invalid or duplicate field type: "%s".', $key));
        }

        $this->types[$key] = $this->definition($key, $label, $settings, false);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->types);
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /** @return array{key:string,label:string,settings:array<string,bool>,built_in:bool} */
    public function get(string $key): array
    {
        if (! isset($this->types[$key])) {
            throw new InvalidArgumentException(sprintf('Unknown field type: "%s".', $key));
        }

        return $this->types[$key];
    }

    /** @return array<string,array<string,bool>> */
    private function builtIns(): array
    {
        $standard = ['options' => false, 'default_value' => true];
        $choices  = ['options' => true, 'default_value' => true];

        return [
            'text' => $standard,
            'email' => $standard,
            'phone' => $standard,
            'number' => $standard,
            'textarea' => $standard,
            'select' => $choices,
            'multi-select' => $choices,
            'radio' => $choices,
            'checkbox' => $choices,
            'date' => $standard,
            'time' => $standard,
            'url' => $standard,
            'hidden' => $standard,
            'consent' => $choices,
            'rating' => $standard,
            'step' => ['options' => false, 'default_value' => false],
        ];
    }

    /**
     * @param array<string,bool> $settings
     * @return array{key:string,label:string,settings:array<string,bool>,built_in:bool}
     */
    private function definition(string $key, string $label, array $settings, bool $builtIn): array
    {
        return ['key' => $key, 'label' => $label, 'settings' => $settings, 'built_in' => $builtIn];
    }
}
