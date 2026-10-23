<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Support\Config\Truthy;

/**
 * The control for each type of settings field, shared by the Settings pages and option pages
 * (spec 032, spec 060 M6, spec 119).
 *
 * Every control says what its stored value is (`data-corex-saved`), so the page can tell an
 * unsaved change from a saved value and put it back (US-SET-01). A field locked by the site's
 * `.env` or the network shows the value in force, cannot be edited, and says where it is set
 * (US-SET-02). An on/off field is a switch (US-SET-09). A secret is never drawn back, and one with a
 * stored value can be removed (US-SET-15). Every value is escaped where it is written.
 */
final class SettingsControls
{
    /**
     * @param array{label:string,type:string,options?:array<string,string>,remove_effect?:string} $field
     * @param bool                                                                                $readOnly the section's add-on is off
     */
    public function control(string $key, array $field, FieldState $state, bool $readOnly = false): string
    {
        $name     = self::nameOf($key);
        $readOnly = $readOnly || $state->locked();

        $control = match ($field['type']) {
            'media'      => $this->media($name, $state, $readOnly),
            'select'     => $this->select($name, $field['options'] ?? [], $state, $readOnly),
            'checkbox'   => $this->switch($name, $state, $readOnly),
            'password'   => $this->secret($key, $field, $state, $readOnly),
            'info'       => $this->info($state->shown),
            // A text box, not the browser's colour picker: a picker cannot be left empty, and
            // empty is how a site says "CoreX's own".
            'hex-colour' => $this->input($name, 'text', $state, $readOnly),
            default      => $this->input($name, $field['type'], $state, $readOnly),
        };

        return $control . $this->lockNote($name, $state);
    }

    /** The name a field has in the form: its key with dots as underscores. */
    public static function nameOf(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    private function lockNote(string $name, FieldState $state): string
    {
        if ($state->lock === null) {
            return '';
        }

        return sprintf(
            '<p class="corex-field-lock" id="%1$s-lock"><span class="dashicons dashicons-lock" aria-hidden="true"></span>%2$s</p>',
            esc_attr($name),
            esc_html($state->lock->label()),
        );
    }

    /** The attributes that tie a control to its lock note and stop it being edited. */
    private function readOnlyAttrs(string $name, FieldState $state, bool $readOnly, string $how): string
    {
        $attrs = $readOnly ? ' ' . $how : '';

        if ($state->lock !== null) {
            $attrs .= ' aria-describedby="' . esc_attr($name . '-lock') . '"';
        }

        return $attrs;
    }

    /**
     * A read-only informational control — no input, never submitted (so it is skipped on save).
     * Used for live status read-outs such as the Media add-on's server-support summary.
     */
    private function info(string $value): string
    {
        return '<p class="corex-field-info">' . esc_html($value) . '</p>';
    }

    private function input(string $name, string $type, FieldState $state, bool $readOnly): string
    {
        return sprintf(
            '<input id="%1$s" name="%1$s" type="%2$s" value="%3$s" class="regular-text" data-corex-saved="%4$s"%5$s />',
            esc_attr($name),
            esc_attr($type),
            esc_attr($state->shown),
            esc_attr($state->saved),
            $this->readOnlyAttrs($name, $state, $readOnly, 'readonly aria-readonly="true"'),
        );
    }

    /**
     * An on/off setting as the switch the Add-ons screen draws (spec 119, US-SET-09).
     *
     * A real checkbox with the switch role, drawn as the switch's track, so Space, its label, its
     * focus ring and the saved "1"/"0" are the browser's. On and Off are both written; the stylesheet shows the one that matches, so it
     * reads right without scripts. Both are hidden from assistive technology, which announces
     * the switch's own state.
     */
    private function switch(string $name, FieldState $state, bool $readOnly): string
    {
        $on = Truthy::of($state->shown);

        return sprintf(
            '<label class="corex-switch"><span class="corex-switch__control">'
            . '<input id="%1$s" name="%1$s" type="checkbox" value="1"%2$s role="switch"'
            . ' class="corex-switch__input" data-corex-saved="%3$s"%4$s />'
            . '<span class="corex-toggle__knob" aria-hidden="true"></span></span>'
            . '<span class="corex-switch__state" aria-hidden="true"><span class="corex-switch__on">%5$s</span>'
            . '<span class="corex-switch__off">%6$s</span></span></label>',
            esc_attr($name),
            $on ? ' checked' : '',
            Truthy::of($state->saved) ? '1' : '0',
            $this->readOnlyAttrs($name, $state, $readOnly, 'disabled'),
            esc_html__('On', 'corex'),
            esc_html__('Off', 'corex'),
        );
    }

    /**
     * A write-only secret (spec 060 M6): the stored value is never drawn back, the input is always
     * empty, and it says whether a value is saved. Left empty, the saved value is kept. One with a
     * stored value can be removed (spec 119, US-SET-15, DECISIONS #446): a box to tick, which the
     * page's script turns into a confirmed "Remove" button. One set in `.env` is a line saying so.
     *
     * @param array{label:string,remove_effect?:string} $field
     */
    private function secret(string $key, array $field, FieldState $state, bool $readOnly): string
    {
        $name = self::nameOf($key);

        if ($state->locked()) {
            return '<p class="corex-secret-state corex-secret-state--locked">' . esc_html__('Set in .env', 'corex') . '</p>';
        }

        $html = sprintf(
            '<input id="%1$s" name="%1$s" type="password" value="" autocomplete="new-password"'
            . ' class="regular-text" placeholder="%2$s" data-corex-saved=""%3$s />'
            . ' <span class="corex-secret-state">%4$s</span>',
            esc_attr($name),
            $state->secretSaved ? esc_attr__('Leave blank to keep the saved value', 'corex') : '',
            $readOnly ? ' readonly aria-readonly="true"' : '',
            $state->secretSaved ? esc_html__('A value is saved', 'corex') : esc_html__('No value saved', 'corex'),
        );

        return $state->secretSaved && ! $readOnly ? $html . $this->removal($key, (string) ($field['remove_effect'] ?? '')) : $html;
    }

    /** The box to tick that removes a saved secret when the page is saved, with what stops working. */
    private function removal(string $key, string $effect): string
    {
        $name = self::nameOf($key);

        return sprintf(
            '<span class="corex-secret-remove" data-corex-secret-remove="%1$s">'
            . '<input type="checkbox" id="%1$s-remove" name="%2$s[]" value="%3$s" data-corex-saved="0"'
            . ' aria-describedby="%1$s-remove-effect" />'
            . '<label for="%1$s-remove">%4$s</label></span>'
            . '<p class="corex-field-help corex-secret-remove__effect" id="%1$s-remove-effect">%5$s</p>',
            esc_attr($name),
            esc_attr(SettingsSaver::REMOVE),
            esc_attr($key),
            esc_html__('Remove the saved value when I save', 'corex'),
            esc_html($effect),
        );
    }

    /**
     * The admin-logo control (design: Settings Brand tab): a framed preview of the saved logo, or a
     * designed empty placeholder, with Select/Change and Remove driving it through the page's
     * script; the address field keeps it editable without scripts.
     */
    private function media(string $name, FieldState $state, bool $readOnly): string
    {
        $hasValue = $state->shown !== '';
        $disabled = $readOnly ? ' disabled' : '';

        return sprintf(
            '<div class="corex-media">'
            . '<div class="corex-media__frame">'
            . '<img class="corex-media-preview" src="%2$s" alt=""%7$s />'
            . '<span class="corex-media__placeholder"%8$s>%3$s</span></div>'
            . '<div class="corex-media__controls">'
            . '<button type="button" class="button corex-media-select" data-target="%1$s"%6$s>%4$s</button>'
            . '<button type="button" class="button corex-media-remove" data-target="%1$s"%6$s>%5$s</button>'
            . '<input id="%1$s" name="%1$s" type="url" value="%9$s" class="corex-media__url regular-text"'
            . ' data-corex-saved="%10$s"%11$s /></div></div>',
            esc_attr($name),
            $hasValue ? esc_url($state->shown) : '',
            esc_html__('No logo set', 'corex'),
            $hasValue ? esc_html__('Change image', 'corex') : esc_html__('Select image', 'corex'),
            esc_html__('Remove', 'corex'),
            $disabled,
            $hasValue ? '' : ' hidden',
            $hasValue ? ' hidden' : '',
            esc_attr($state->shown),
            esc_attr($state->saved),
            $this->readOnlyAttrs($name, $state, $readOnly, 'readonly aria-readonly="true"'),
        );
    }

    /**
     * @param array<string,string> $options value => label
     */
    private function select(string $name, array $options, FieldState $state, bool $readOnly): string
    {
        // A browser selects the first choice when none matches, so that is what is shown and what
        // counts as stored (an appearance never chosen shows "System").
        $shown = self::chosen($options, $state->shown);
        $saved = self::chosen($options, $state->saved);

        // `data-corex-select` opts this into the approved CoreX control (DECISIONS #141); the
        // native <select> is still what submits, so the form works without scripts.
        $html = sprintf(
            '<select id="%1$s" name="%1$s" data-corex-select data-corex-saved="%2$s"%3$s>',
            esc_attr($name),
            esc_attr($saved),
            $this->readOnlyAttrs($name, $state, $readOnly, 'disabled'),
        );

        foreach ($options as $optionValue => $label) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr((string) $optionValue),
                (string) $optionValue === $shown ? ' selected' : '',
                esc_html($label),
            );
        }

        return $html . '</select>';
    }

    /**
     * The choice a select shows for a value: the value when it is one of the choices, the first
     * choice otherwise, as a browser draws it.
     *
     * @param array<string,string> $options value => label
     */
    public static function chosen(array $options, string $value): string
    {
        $keys = array_map('strval', array_keys($options));

        return in_array($value, $keys, true) || $keys === [] ? $value : $keys[0];
    }
}
