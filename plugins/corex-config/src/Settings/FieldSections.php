<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * A provider of form sections + their field keys. The built-in `SettingsRegistry` and any custom
 * `OptionPage` both satisfy it, so the one `SettingsForm` (per-type controls) and the one save
 * loop render and persist either with no duplicated form code (spec 039).
 */
interface FieldSections
{
    /**
     * @return array<string,array{title:string,fields:array<string,array{label:string,type:string,options?:array<string,string>}>}>
     */
    public function sections(): array;

    /**
     * @return list<string> every field key across the sections
     */
    public function keys(): array;
}
