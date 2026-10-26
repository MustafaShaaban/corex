<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * Draws settings sections as form rows: a label, the control its type calls for, its help, and
 * the conditions that show it (spec 032, spec 039, spec 060 M6).
 *
 * The Settings pages draw one page's sections through {@see rows()} (spec 119). An option page
 * draws all of its sections through {@see render()}, as tabs with one save button.
 */
final class SettingsForm
{
    public function __construct(
        private readonly FieldSections $registry,
        private readonly SettingsControls $controls = new SettingsControls(),
    ) {
    }

    /**
     * The whole form, as an option page draws it.
     *
     * @param callable(string):(string|FieldState)          $value        current value for a field key
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
        $html .= $this->tablist($sections, $activeKey);

        $states = static function (string $key) use ($value): FieldState {
            $shown = $value($key);

            return $shown instanceof FieldState ? $shown : FieldState::plain((string) $shown);
        };

        foreach ($sections as $sectionKey => $section) {
            $state  = $sectionState !== null ? $sectionState((string) $sectionKey) : null;
            $html  .= sprintf(
                '<section id="corex-settings-section-%1$s" class="corex-settings-section corex-settings-section--%2$s%5$s"'
                . ' role="tabpanel" aria-labelledby="corex-tab-%1$s"><h2>%3$s</h2>%4$s',
                esc_attr((string) $sectionKey),
                esc_attr($state === null ? 'normal' : str_replace('_', '-', $state->value)),
                esc_html($section['title']),
                $this->sectionNotice($state),
                (string) $sectionKey === $activeKey ? ' is-active' : '',
            );

            // A not-installed add-on shows only the notice — never its fields.
            if ($state !== SettingsSectionState::Hidden) {
                $html .= $this->rows((string) $sectionKey, $section, $states, $state === SettingsSectionState::Disabled);
            }

            $html .= '</section>';
        }

        return $html . sprintf(
            '<div class="corex-settings__save"><button type="submit" class="button button-primary">%s</button></div></form>',
            esc_html__('Save settings', 'corex')
        );
    }

    /**
     * One section's fields as a form table.
     *
     * @param array{title:string,fields:array<string,array<string,mixed>>} $section
     * @param callable(string, string):FieldState|callable(string):FieldState $state the field's state, by key (and type)
     * @param bool                                                       $readOnly the section's add-on is off
     */
    public function rows(string $sectionKey, array $section, callable $state, bool $readOnly = false): string
    {
        $html  = '<table class="form-table" role="presentation">';
        $html .= $this->driverNotice($sectionKey, $section, $state);

        foreach ($section['fields'] as $key => $field) {
            $key        = (string) $key;
            $name       = SettingsControls::nameOf($key);
            $fieldState = $state($key, (string) ($field['type'] ?? 'text'));

            $html .= sprintf(
                '<tr%5$s><th><label for="%1$s">%2$s</label></th><td>%3$s%4$s</td></tr>',
                esc_attr($name),
                esc_html((string) $field['label']),
                $this->controls->control($key, $field, $fieldState, $readOnly),
                $this->help($name, $field, $state),
                $this->showForAttrs($field, $state),
            );
        }

        return $html . '</table>';
    }

    /**
     * The accessible tablist (one tab per section, in registry order). Without scripts every panel
     * stays visible.
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
     * Driver-aware visibility (spec 060): a field may declare `show_for` (a controlling field key
     * and the values that reveal it). The row is hidden server-side when the current value does not
     * match, and the page's script toggles it live as the controlling select changes.
     *
     * @param array{show_for?:array{key:string,values:list<string>}} $field
     */
    private function showForAttrs(array $field, callable $state): string
    {
        if (! isset($field['show_for']['key'], $field['show_for']['values'])) {
            return '';
        }

        $key     = (string) $field['show_for']['key'];
        $values  = $field['show_for']['values'];
        $current = $state($key, 'select')->shown;

        return sprintf(
            ' class="corex-settings-row" data-corex-show-for="%s" data-corex-show-values="%s"%s',
            esc_attr($key),
            esc_attr(implode(' ', $values)),
            in_array($current, $values, true) ? '' : ' hidden',
        );
    }

    /**
     * The notice shown in place of the provider fields when the captcha driver is None or the
     * honeypot. Toggled live by the same data attributes the script reads.
     *
     * @param array{fields:array<string,mixed>} $section
     */
    private function driverNotice(string $sectionKey, array $section, callable $state): string
    {
        if ($sectionKey !== 'captcha' || ! isset($section['fields']['captcha.driver'])) {
            return '';
        }

        $current = $state('captcha.driver', 'select')->shown;
        $notices = [
            'none'     => esc_html__('Captcha is disabled — no provider selected.', 'corex'),
            'honeypot' => esc_html__('Honeypot adds a hidden spam-trap field that bots fill in — no provider keys are required.', 'corex'),
        ];

        $out = '';
        foreach ($notices as $driver => $message) {
            $out .= sprintf(
                '<tr class="corex-settings-row corex-driver-notice" data-corex-show-for="captcha.driver"'
                . ' data-corex-show-values="%1$s"%2$s><td colspan="2">'
                . '<p class="corex-driver-notice__msg">%3$s</p></td></tr>',
                esc_attr($driver),
                $driver === $current ? '' : ' hidden',
                $message,
            );
        }

        return $out;
    }

    /**
     * The field's help and an official reference link (new tab, safe rel). A field with
     * `help_variants` gets one line per controlling-driver value, each shown only for its driver.
     *
     * @param array{help?:string,help_url?:string,help_link?:string,help_variants?:array<string,array<string,string>>,show_for?:array{key:string}} $field
     */
    private function help(string $name, array $field, callable $state): string
    {
        if (isset($field['help_variants'], $field['show_for']['key'])) {
            return $this->helpVariants((string) $field['show_for']['key'], $field['help_variants'], $state);
        }

        $text = $this->helpText($field);

        if (trim($text) === '') {
            return '';
        }

        return '<p class="corex-field-help" id="' . esc_attr($name) . '-help">' . $text . '</p>';
    }

    /**
     * @param array<string,array<string,string>> $variants
     */
    private function helpVariants(string $key, array $variants, callable $state): string
    {
        $current = $state($key, 'select')->shown;
        $out     = '';

        foreach ($variants as $driver => $variant) {
            $driver = (string) $driver;
            $out   .= sprintf(
                '<p class="corex-field-help" data-corex-show-for="%1$s" data-corex-show-values="%2$s"%3$s>%4$s</p>',
                esc_attr($key),
                esc_attr($driver),
                $driver === $current ? '' : ' hidden',
                $this->helpText($variant),
            );
        }

        return $out;
    }

    /**
     * @param array{help?:string,help_url?:string,help_link?:string} $field
     */
    private function helpText(array $field): string
    {
        $text = isset($field['help']) ? esc_html($field['help']) : '';

        if (isset($field['help_url']) && $field['help_url'] !== '') {
            $linkText = esc_html($field['help_link'] ?? __('Reference', 'corex'));
            $text    .= ' <a class="corex-field-help__link" href="' . esc_url($field['help_url'])
                . '" target="_blank" rel="noopener noreferrer">' . $linkText
                . ' <span aria-hidden="true">&#8599;</span><span class="screen-reader-text">'
                . esc_html__('(opens in a new tab)', 'corex') . '</span></a>';
        }

        return $text;
    }

    private function sectionNotice(?SettingsSectionState $state): string
    {
        $message = match ($state) {
            SettingsSectionState::Hidden => esc_html__('This add-on is not installed.', 'corex'),
            SettingsSectionState::Disabled => esc_html__('This add-on is inactive — enable it to use these settings.', 'corex'),
            SettingsSectionState::ConfigurationNeeded => esc_html__('Configuration needed.', 'corex'),
            default => '',
        };

        if ($message === '' || $state === null) {
            return '';
        }

        return sprintf(
            '<p class="corex-section-notice corex-section-notice--%1$s" role="status">%2$s</p>',
            esc_attr(str_replace('_', '-', $state->value)),
            $message,
        );
    }
}
