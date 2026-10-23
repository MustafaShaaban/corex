<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * What one Settings save did (spec 119, US-SET-01): the page saved, the fields refused (each with
 * its name in the form, label, type and what was typed, as spec 106 story 5 reports them), and the
 * secrets removed.
 */
final readonly class SettingsSaveResult
{
    /**
     * @param list<array{name:string,label:string,type:string,typed:string}> $refused
     * @param list<string>                                                   $removed setting keys
     */
    public function __construct(
        public string $page,
        public array $refused = [],
        public array $removed = [],
    ) {
    }

    /** What was typed into a refused field, by its name in the form; null when it was not refused. */
    public function typedInto(string $name): ?string
    {
        foreach ($this->refused as $field) {
            if ($field['name'] === $name) {
                return $field['typed'];
            }
        }

        return null;
    }

    /** @return array{page:string,refused:list<array{name:string,label:string,type:string,typed:string}>,removed:list<string>} */
    public function toArray(): array
    {
        return ['page' => $this->page, 'refused' => $this->refused, 'removed' => $this->removed];
    }

    /** @param array<string,mixed> $stored */
    public static function fromArray(array $stored): self
    {
        $refused = [];
        foreach ((array) ($stored['refused'] ?? []) as $field) {
            if (is_array($field)) {
                $refused[] = [
                    'name'  => (string) ($field['name'] ?? ''),
                    'label' => (string) ($field['label'] ?? ''),
                    'type'  => (string) ($field['type'] ?? ''),
                    'typed' => (string) ($field['typed'] ?? ''),
                ];
            }
        }

        return new self(
            (string) ($stored['page'] ?? ''),
            $refused,
            array_values(array_map('strval', (array) ($stored['removed'] ?? []))),
        );
    }
}
