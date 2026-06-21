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
    /**
     * @param callable(string):string                       $value        current value for a field key
     * @param (callable(string):?SettingsSectionState)|null $sectionState per-section runtime state by section key
     * @param string                                        $activeKey    the section to show first (a tab key)
     */
    public function render(callable $value, string $nonceField, ?callable $sectionState = null, string $activeKey = ''): string
    {
        $sections = $this->registry->sections();
        $keys     = array_keys($sections);

        if ($activeKey === '' || ! in_array($activeKey, $keys, true)) {
            $activeKey = (string) ($keys[0] ?? '');
        }

        $html  = '<form method="post" action="" class="corex-settings-form">' . $nonceField;
        $html .= '<input type="hidden" name="corex_tab" value="' . esc_attr($activeKey)
            . '" class="corex-settings__active-tab" />';
        $html .= $this->tablist($sections, $activeKey);

        foreach ($sections as $sectionKey => $section) {
            $html .= $this->panel((string) $sectionKey, $section, $value, $sectionState, $activeKey);
        }

        return $html . sprintf(
            '<div class="corex-settings__save"><button type="submit" class="button button-primary">%s</button></div></form>',
            esc_html__('Save settings', 'corex')
        );
    }

    /**
     * The accessible tablist (one tab per real settings section, in registry order). Buttons
     * carry the ARIA tab semantics; a small enqueued script shows one panel at a time and wires
     * arrow-key navigation. Without JS every panel stays visible (a stacked, usable fallback).
     *
     * @param array<string,array{title:string,fields:array<string,mixed>}> $sections
     */
    private function tablist(array $sections, string $activeKey): string
    {
        $html = '<div class="corex-settings-tabs" role="tablist" aria-label="'
            . esc_attr__('CoreX settings sections', 'corex') . '">';

        foreach ($sections as $sectionKey => $section) {
            $isActive = (string) $sectionKey === $activeKey;
            $html    .= sprintf(
                '<button type="button" class="corex-settings-tab%5$s" role="tab" id="corex-tab-%1$s"'
                . ' aria-controls="corex-settings-section-%1$s" aria-selected="%2$s" tabindex="%3$s"'
                . ' data-corex-tab="%1$s">%4$s</button>',
                esc_attr((string) $sectionKey),
                $isActive ? 'true' : 'false',
                $isActive ? '0' : '-1',
                esc_html($section['title']),
                $isActive ? ' is-active' : '',
            );
        }

        return $html . '</div>';
    }

    /**
     * One section rendered as an ARIA tabpanel.
     *
     * @param array{title:string,fields:array<string,array{label:string,type:string,options?:array<string,string>,help?:string}>} $section
     * @param callable(string):string                       $value
     * @param (callable(string):?SettingsSectionState)|null $sectionState
     */
    private function panel(string $sectionKey, array $section, callable $value, ?callable $sectionState, string $activeKey): string
    {
        $state      = $sectionState !== null ? $sectionState($sectionKey) : null;
        $stateClass = $state === null ? 'normal' : str_replace('_', '-', $state->value);
        $isActive   = $sectionKey === $activeKey;

        $html = sprintf(
            '<section id="corex-settings-section-%1$s" class="corex-settings-section corex-settings-section--%2$s%5$s"'
            . ' role="tabpanel" aria-labelledby="corex-tab-%1$s"><h2>%3$s</h2>%4$s',
            esc_attr($sectionKey),
            esc_attr($stateClass),
            esc_html($section['title']),
            $this->sectionNotice($state),
            $isActive ? ' is-active' : '',
        );

        // A not-installed add-on shows only the notice — never its fields.
        if ($state === SettingsSectionState::Hidden) {
            return $html . '</section>';
        }

        $disabled = $state === SettingsSectionState::Disabled;
        $html    .= '<table class="form-table">';

        foreach ($section['fields'] as $key => $field) {
            $name = str_replace('.', '_', $key);

            $html .= sprintf(
                '<tr><th><label for="%s">%s</label></th><td>%s%s</td></tr>',
                esc_attr($name),
                esc_html($field['label']),
                $this->control($name, $field, (string) $value($key), $disabled),
                $this->help($name, $field),
            );
        }

        return $html . '</table></section>';
    }

    /**
     * The field's helper copy and, for external key/token fields, an official reference link
     * that opens in a new tab with safe rel attributes. Concise help, not a docs block — and it
     * never reveals a saved secret (it only points to where the key comes from).
     *
     * @param array{help?:string,help_url?:string,help_link?:string} $field
     */
    private function help(string $name, array $field): string
    {
        $text = isset($field['help']) ? esc_html($field['help']) : '';

        if (isset($field['help_url']) && $field['help_url'] !== '') {
            $linkText = esc_html($field['help_link'] ?? __('Reference', 'corex'));
            $text    .= ' <a class="corex-field-help__link" href="' . esc_url($field['help_url'])
                . '" target="_blank" rel="noopener noreferrer">' . $linkText
                . ' <span aria-hidden="true">&#8599;</span><span class="screen-reader-text">'
                . esc_html__('(opens in a new tab)', 'corex') . '</span></a>';
        }

        if (trim($text) === '') {
            return '';
        }

        return '<p class="corex-field-help" id="' . esc_attr($name) . '-help">' . $text . '</p>';
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

        if ($message === '' || $state === null) {
            return '';
        }

        $modifier = str_replace('_', '-', $state->value);

        return sprintf(
            '<p class="corex-section-notice corex-section-notice--%1$s" role="status">%2$s</p>',
            esc_attr($modifier),
            $message,
        );
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

    /**
     * The admin-logo control (design: Settings Brand tab): a framed preview that shows the
     * current saved logo as a thumbnail, or a designed empty placeholder when none is set, so
     * the admin can always tell whether the setting has a value. Select/Change + Remove drive
     * the preview live via the enqueued media script; the URL field keeps it editable without
     * JS. No base64 — a normal attachment URL.
     */
    private function media(string $name, string $value, bool $disabled = false): string
    {
        $hasValue = $value !== '';

        return sprintf(
            '<div class="corex-media">'
            . '<div class="corex-media__frame">'
            . '<img class="corex-media-preview" src="%2$s" alt=""%7$s />'
            . '<span class="corex-media__placeholder"%8$s>%3$s</span></div>'
            . '<div class="corex-media__controls">'
            . '<button type="button" class="button corex-media-select" data-target="%1$s"%6$s>%4$s</button>'
            . '<button type="button" class="button corex-media-remove" data-target="%1$s"%6$s>%5$s</button>'
            . '<input id="%1$s" name="%1$s" type="url" value="%9$s" class="corex-media__url regular-text"%6$s /></div></div>',
            esc_attr($name),
            $hasValue ? esc_url($value) : '',
            esc_html__('No logo set', 'corex'),
            $hasValue ? esc_html__('Change image', 'corex') : esc_html__('Select image', 'corex'),
            esc_html__('Remove', 'corex'),
            $this->disabledAttr($disabled),
            $hasValue ? '' : ' hidden',
            $hasValue ? ' hidden' : '',
            esc_attr($value),
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
