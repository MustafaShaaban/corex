<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Options;

defined('ABSPATH') || exit;

/**
 * The options CoreX reads on every request, kept among the options WordPress loads with the
 * site's, so that reading one is no query (DECISIONS #366).
 *
 * An option stored with autoload off is a query each time a request first asks for it, and so is
 * an option with no row, because WordPress remembers a missing option for one request only. A
 * store whose option is read on every request registers it here, with the value that says nothing
 * is stored, and writes it through {@see self::store()}.
 *
 * Nothing here answers for an option. A store goes on reading with `get_option()`, which is right
 * for a row that is loaded, for one that is not, and for none: where a row is kept decides what a
 * read costs and never what it answers.
 */
final class LoadedOptions
{
    /** @var array<string, mixed> Option name to the value that says nothing is stored. */
    private array $nothingStored = [];

    /**
     * Name an option that is read on every request.
     *
     * @param mixed $nothingStored What its row holds on a site that has stored nothing. The store
     *                             must read this value exactly as it reads a missing option.
     */
    public function register(string $option, mixed $nothingStored): void
    {
        $this->nothingStored[$option] = $nothingStored;
    }

    /**
     * Store an option's value where WordPress loads it with the site's options.
     */
    public static function store(string $option, mixed $value): void
    {
        update_option($option, $value, true);

        // update_option() leaves a row's autoload as it was when the value is the one it holds.
        wp_set_option_autoload($option, true);
    }

    /**
     * Turn over each registered option's row that is not loaded, and write the row of one that has
     * none. Only an admin or a cron request writes, as with the schema self-heal; once every row
     * is loaded this asks the database nothing.
     */
    public function ensureLoaded(): void
    {
        $notLoaded = array_diff_key($this->nothingStored, wp_load_alloptions());

        if ($notLoaded === [] || ! (is_admin() || wp_doing_cron())) {
            return;
        }

        $turnedOver = array_filter(
            wp_set_option_autoload_values(array_fill_keys(array_keys($notLoaded), true)),
        );

        foreach (array_diff_key($notLoaded, $turnedOver) as $option => $nothingStored) {
            $this->insertIfAbsent($option, $nothingStored);
        }
    }

    /**
     * Not add_option(): this request may believe the option is missing because it asked before
     * another request saved it, and add_option() then replaces the row it finds. A row that is
     * already there is left as it is.
     */
    private function insertIfAbsent(string $option, mixed $nothingStored): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'on')",
            $option,
            maybe_serialize($nothingStored),
        ));

        // Whichever request wrote the row, what this one remembers of the option is out of date.
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
        wp_cache_delete($option, 'options');
    }
}
