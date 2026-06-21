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
 *
 * Spec 060 / M6 US2: an optional per-section state callable lets the form reflect each
 * section's runtime add-on state — a not-installed section is hidden behind a notice, an
 * inactive section shows a disabled notice and disables its inputs, and an active-but-
 * unconfigured section shows a "configuration needed" prompt with its (enterable) fields.
 */
final class SettingsForm
{
    public function __construct(private readonly FieldSections $registry)
    {
    }

    /**
     * @param callable(string):string                       $value        current value for a field key
     * @param (callable(string):?SettingsSectionState)|null $sectionState per-section runtime state by section key
     */
    public function render(callable $value, string $nonceField, ?callable $sectionState = null): string
    {
        $html = '<form method="post" action="">' . $nonceField;

        foreach ($this->registry->sections() as $sectionKey => $section) {
            $state = $sectionState !== null ? $sectionState((string) $sectionKey) : null;

            $html .= sprintf('<h2>%s</h2>', esc_html($section['title']));
            $html .= $this->sectionNotice($state);

            // A not-installed add-on shows only the notice — never its fields.
            if ($state === SettingsSectionState::Hidden) {
                continue;
            }

            $disabled = $state === SettingsSectionState::Disabled;
            $html    .= '<table class="form-table">';

            foreach ($section['fields'] as $key => $field) {
                $name = str_replace('.', '_', $key);

                $html .= sprintf(
                    '<tr><th><label for="%s">%s</label></th><td>%s</td></tr>',
                    esc_attr($name),
                    esc_html($field['label']),
                    $this->control($name, $field, (string) $value($key), $disabled),
                );
            }

            $html .= '</table>';
        }

        return $html . sprintf(
            '<p><button type="submit" class="button button-primary">%s</button></p></form>',
            esc_html__('Save settings', 'corex')
        );
    }

    private function sectionNotice(?SettingsSectionState $state): string
    {
        $message = match ($state) {
            SettingsSectionState::Hidden => esc_html__('This add-on is not installed.', 'corex'),
            SettingsSectionState::Disabled => esc_html__(
                'This add-on is inactive — enable it to use these settings.',
                'corex',
            ),
            SettingsSectionState::ConfigurationNeeded => esc_html__('Configuration needed.', 'corex'),
            default => '',
        };

        return $message === '' ? '' : sprintf('<p class="corex-section-notice">%s</p>', $message);
    }

    /**
     * @param array{label:string,type:string,options?:array<string,string>} $field
     */
    private function control(string $name, array $field, string $value, bool $disabled = false): string
    {
        return match ($field['type']) {
            'media'    => $this->media($name, $value, $disabled),
            'select'   => $this->select($name, $field['options'] ?? [], $value, $disabled),
            'checkbox' => $this->checkbox($name, $value, $disabled),
            'password' => $this->secret($name, $value !== '', $disabled),
            default    => $this->input($name, $field['type'], $value, $disabled),
        };
    }

    private function disabledAttr(bool $disabled): string
    {
        return $disabled ? ' disabled' : '';
    }

    private function input(string $name, string $type, string $value, bool $disabled = false): string
    {
        return sprintf(
            '<input id="%1$s" name="%1$s" type="%2$s" value="%3$s" class="regular-text"%4$s />',
            esc_attr($name),
            esc_attr($type),
            esc_attr($value),
            $this->disabledAttr($disabled),
        );
    }

    /**
     * A write-only secret control (spec 060 / M6 US2): the stored secret is never
     * rendered back — the input is always empty and only a set/not-set hint is shown,
     * so the value cannot leak via the page source. An empty submit preserves the
     * stored secret (see the settings save loop).
     */
    private function secret(string $name, bool $isSet, bool $disabled = false): string
    {
        return sprintf(
            '<input id="%1$s" name="%1$s" type="password" value="" autocomplete="new-password"'
            . ' class="regular-text" placeholder="%2$s"%4$s />'
            . ' <span class="corex-secret-state">%3$s</span>',
            esc_attr($name),
            $isSet ? esc_attr__('Leave blank to keep the saved value', 'corex') : esc_attr__('Not set', 'corex'),
            $isSet ? esc_html__('Saved', 'corex') : esc_html__('Not set', 'corex'),
            $this->disabledAttr($disabled),
        );
    }

    private function media(string $name, string $value, bool $disabled = false): string
    {
        $preview = $value === ''
            ? '<img class="corex-media-preview" src="" alt="" style="display:none" />'
            : sprintf('<img class="corex-media-preview" src="%s" alt="" />', esc_url($value));

        return sprintf(
            '<input id="%1$s" name="%1$s" type="url" value="%2$s" class="regular-text"%6$s />'
            . ' <button type="button" class="button corex-media-select" data-target="%1$s"%6$s>%3$s</button>'
            . ' <button type="button" class="button corex-media-remove" data-target="%1$s"%6$s>%4$s</button>'
            . '<br />%5$s',
            esc_attr($name),
            esc_attr($value),
            esc_html__('Select image', 'corex'),
            esc_html__('Remove', 'corex'),
            $preview,
            $this->disabledAttr($disabled),
        );
    }

    /**
     * @param array<string,string> $options value => label
     */
    private function select(string $name, array $options, string $value, bool $disabled = false): string
    {
        $html = sprintf('<select id="%1$s" name="%1$s"%2$s>', esc_attr($name), $this->disabledAttr($disabled));

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

    private function checkbox(string $name, string $value, bool $disabled = false): string
    {
        return sprintf(
            '<input id="%1$s" name="%1$s" type="checkbox" value="1"%2$s%3$s />',
            esc_attr($name),
            $value !== '' && $value !== '0' ? ' checked' : '',
            $this->disabledAttr($disabled),
        );
    }
}
