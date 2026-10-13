<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

/**
 * An option as the database holds it: its value, and whether WordPress loads it with the site's
 * options. `get_option()` answers the same for a loaded row and an unloaded one, so a test of
 * where a row is kept has to read the row.
 */
final class OptionRow
{
    /**
     * The row's `autoload` column, or null when the option has no row.
     */
    public static function autoload(string $option): ?string
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option),
        );
    }

    /**
     * Store an option with autoload off, replacing any row it has.
     */
    public static function storeUnloaded(string $option, mixed $value): void
    {
        delete_option($option);
        add_option($option, $value, '', false);
    }

    /**
     * What a test reads before it changes an option, to hand to {@see self::restore()}.
     *
     * @return array{value: mixed, autoload: string}|null Null when the option has no row.
     */
    public static function capture(string $option): ?array
    {
        $autoload = self::autoload($option);

        return $autoload === null ? null : ['value' => get_option($option), 'autoload' => $autoload];
    }

    /**
     * Put an option back as {@see self::capture()} found it, including "it had no row".
     *
     * @param array{value: mixed, autoload: string}|null $row
     */
    public static function restore(string $option, ?array $row): void
    {
        delete_option($option);

        if ($row === null) {
            return;
        }

        add_option($option, $row['value'], '', in_array($row['autoload'], wp_autoload_values_to_autoload(), true));
    }
}
