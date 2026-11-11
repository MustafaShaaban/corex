<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * What one Settings save did (spec 119, US-SET-01): the page saved, the fields refused (each with
 * its name in the form, label, type and what was typed, as spec 106 story 5 reports them), the
 * secrets removed, and whether anything was saved at all: a post that names no Settings page saves
 * nothing, and says so.
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
        public bool $saved = true,
    ) {
    }

    /** A save that did not say which page it came from: nothing was written. */
    public static function nothingSaved(string $page): self
    {
        return new self($page, [], [], false);
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

    /** @return array{page:string,refused:list<array{name:string,label:string,type:string,typed:string}>,removed:list<string>,saved:bool} */
    public function toArray(): array
    {
        return ['page' => $this->page, 'refused' => $this->refused, 'removed' => $this->removed, 'saved' => $this->saved];
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
            (bool) ($stored['saved'] ?? true),
        );
    }
}
