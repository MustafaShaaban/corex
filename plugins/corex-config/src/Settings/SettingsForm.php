<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * Builds the settings form HTML from the registry — every value escaped. Pure (it
 * takes a value resolver + the pre-built nonce field). The React/DataForm UI is the
 * deferred enhancement; this is the server-rendered MVP.
 */
final class SettingsForm
{
    public function __construct(private readonly SettingsRegistry $registry)
    {
    }

    /**
     * @param callable(string):string $value current value for a field key
     */
    public function render(callable $value, string $nonceField): string
    {
        $html = '<form method="post" action="">' . $nonceField;

        foreach ($this->registry->sections() as $section) {
            $html .= sprintf('<h2>%s</h2><table class="form-table">', esc_html($section['title']));

            foreach ($section['fields'] as $key => $field) {
                $name = str_replace('.', '_', $key);

                $html .= sprintf(
                    '<tr><th><label for="%1$s">%2$s</label></th><td><input id="%1$s" name="%1$s" type="%3$s" value="%4$s" class="regular-text" /></td></tr>',
                    esc_attr($name),
                    esc_html($field['label']),
                    esc_attr($field['type']),
                    esc_attr($value($key))
                );
            }

            $html .= '</table>';
        }

        return $html . sprintf(
            '<p><button type="submit" class="button button-primary">%s</button></p></form>',
            esc_html__('Save settings', 'corex')
        );
    }
}
