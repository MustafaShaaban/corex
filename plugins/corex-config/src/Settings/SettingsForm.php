<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * Builds the settings form HTML from the registry — every value escaped, and the right
 * control per field type (text/email/url/password input, a media picker, a select, a
 * checkbox). Pure: it takes a value resolver + the pre-built nonce field. The media picker's
 * wp.media wiring is a small enqueued script; the field degrades to an editable value
 * without JS (spec 032).
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
                    '<tr><th><label for="%s">%s</label></th><td>%s</td></tr>',
                    esc_attr($name),
                    esc_html($field['label']),
                    $this->control($name, $field, (string) $value($key)),
                );
            }

            $html .= '</table>';
        }

        return $html . sprintf(
            '<p><button type="submit" class="button button-primary">%s</button></p></form>',
            esc_html__('Save settings', 'corex')
        );
    }

    /**
     * @param array{label:string,type:string,options?:array<string,string>} $field
     */
    private function control(string $name, array $field, string $value): string
    {
        return match ($field['type']) {
            'media'    => $this->media($name, $value),
            'select'   => $this->select($name, $field['options'] ?? [], $value),
            'checkbox' => $this->checkbox($name, $value),
            default    => $this->input($name, $field['type'], $value),
        };
    }

    private function input(string $name, string $type, string $value): string
    {
        return sprintf(
            '<input id="%1$s" name="%1$s" type="%2$s" value="%3$s" class="regular-text" />',
            esc_attr($name),
            esc_attr($type),
            esc_attr($value),
        );
    }

    private function media(string $name, string $value): string
    {
        $preview = $value === ''
            ? '<img class="corex-media-preview" src="" alt="" style="display:none" />'
            : sprintf('<img class="corex-media-preview" src="%s" alt="" />', esc_url($value));

        return sprintf(
            '<input id="%1$s" name="%1$s" type="url" value="%2$s" class="regular-text" />'
            . ' <button type="button" class="button corex-media-select" data-target="%1$s">%3$s</button>'
            . ' <button type="button" class="button corex-media-remove" data-target="%1$s">%4$s</button>'
            . '<br />%5$s',
            esc_attr($name),
            esc_attr($value),
            esc_html__('Select image', 'corex'),
            esc_html__('Remove', 'corex'),
            $preview,
        );
    }

    /**
     * @param array<string,string> $options value => label
     */
    private function select(string $name, array $options, string $value): string
    {
        $html = sprintf('<select id="%1$s" name="%1$s">', esc_attr($name));

        foreach ($options as $optionValue => $label) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr((string) $optionValue),
                (string) $optionValue === $value ? ' selected' : '',
                esc_html($label),
            );
        }

        return $html . '</select>';
    }

    private function checkbox(string $name, string $value): string
    {
        return sprintf(
            '<input id="%1$s" name="%1$s" type="checkbox" value="1"%2$s />',
            esc_attr($name),
            $value !== '' && $value !== '0' ? ' checked' : '',
        );
    }
}
