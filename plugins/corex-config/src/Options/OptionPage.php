<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Options;

defined('ABSPATH') || exit;

use Corex\Config\Settings\FieldSections;

/**
 * A declarative admin option page: a title, where it lives in the menu, who may see it, and a flat
 * list of fields. Because it is a {@see FieldSections}, the existing spec-032 `SettingsForm`
 * renders it and the existing save loop persists it — a developer adds a real, secured settings
 * screen with one declaration and no form/nonce/save code (spec 039).
 */
final class OptionPage implements FieldSections
{
    /**
     * @param list<array{key:string,label:string,type:string,options?:array<string,string>}> $fields
     */
    public function __construct(
        private readonly string $slug,
        private readonly string $title,
        private readonly string $menuLabel,
        private readonly string $capability,
        private readonly string $parent,
        private readonly array $fields,
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function menuLabel(): string
    {
        return $this->menuLabel;
    }

    public function capability(): string
    {
        return $this->capability;
    }

    /** Parent menu slug, or '' for a top-level page. */
    public function parent(): string
    {
        return $this->parent;
    }

    /**
     * @return array<string,array{title:string,fields:array<string,array{label:string,type:string,options?:array<string,string>}>}>
     */
    public function sections(): array
    {
        $fields = [];

        foreach ($this->fields as $field) {
            $definition = ['label' => $field['label'], 'type' => $field['type']];

            if (isset($field['options'])) {
                $definition['options'] = $field['options'];
            }

            $fields[$field['key']] = $definition;
        }

        return [$this->slug => ['title' => $this->title, 'fields' => $fields]];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(static fn (array $field): string => $field['key'], $this->fields);
    }
}
