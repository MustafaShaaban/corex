<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Forms\Schema\FieldSchema;

/**
 * Renders one form field to accessible, token-only, escaped HTML — the whole field
 * (wrapper + label/legend + control + error region), because label semantics depend
 * on the control type (a single input uses `<label for>`; a radio/checkbox group uses
 * `<fieldset><legend>`). Supports every input type the form builder exposes plus the
 * per-field presentation knobs (label mode, column width, custom class + attributes).
 * Pure of business logic; FormBlockRenderer composes these into the form.
 */
final class FieldRenderer
{
    private const INPUT_TYPES = ['text', 'email', 'number', 'tel', 'url', 'password', 'date', 'file'];

    public function render(string $slug, FieldSchema $field): string
    {
        $id = 'corex-' . $slug . '-' . $field->name;
        $error = sprintf('<span class="corex-form__error" id="%s-error" role="alert"></span>', esc_attr($id));

        if ($field->isChoice() && $field->type !== 'select') {
            return $this->group($id, $field, $error);
        }

        return sprintf(
            '<div class="%1$s" data-corex-field="%2$s">%3$s%4$s%5$s</div>',
            esc_attr($this->wrapperClass($field)),
            esc_attr($field->name),
            $this->label($id, $field),
            $this->control($id, $field),
            $error,
        );
    }

    private function control(string $id, FieldSchema $field): string
    {
        return match ($field->type) {
            'textarea'       => $this->textarea($id, $field),
            'select'         => $this->select($id, $field),
            'checkbox', 'toggle' => $this->checkbox($id, $field),
            default          => $this->input($id, $field),
        };
    }

    private function input(string $id, FieldSchema $field): string
    {
        $type = in_array($field->type, self::INPUT_TYPES, true)
            ? ($field->type === 'email' ? 'email' : $field->type)
            : 'text';

        return sprintf(
            '<input id="%1$s" name="%2$s" type="%3$s" class="%4$s" aria-describedby="%1$s-error"%5$s%6$s />',
            esc_attr($id),
            esc_attr($field->name),
            esc_attr($type),
            esc_attr($this->controlClass($field)),
            $this->requiredAttr($field),
            $this->extraAttrs($field),
        );
    }

    private function textarea(string $id, FieldSchema $field): string
    {
        return sprintf(
            '<textarea id="%1$s" name="%2$s" class="%3$s" aria-describedby="%1$s-error"%4$s%5$s></textarea>',
            esc_attr($id),
            esc_attr($field->name),
            esc_attr($this->controlClass($field)),
            $this->requiredAttr($field),
            $this->extraAttrs($field),
        );
    }

    private function select(string $id, FieldSchema $field): string
    {
        $options = '';

        foreach ($field->options as $value => $label) {
            $options .= sprintf('<option value="%1$s">%2$s</option>', esc_attr($value), esc_html($label));
        }

        return sprintf(
            '<select id="%1$s" name="%2$s" class="%3$s" aria-describedby="%1$s-error"%4$s%5$s>%6$s</select>',
            esc_attr($id),
            esc_attr($field->name),
            esc_attr($this->controlClass($field)),
            $this->requiredAttr($field),
            $this->extraAttrs($field),
            $options,
        );
    }

    /**
     * A single boolean checkbox (or toggle) — label sits inline after the box.
     */
    private function checkbox(string $id, FieldSchema $field): string
    {
        $class = 'corex-form__checkbox' . ($field->type === 'toggle' ? ' corex-form__toggle' : '');

        return sprintf(
            '<input id="%1$s" name="%2$s" type="checkbox" value="1" class="%3$s" aria-describedby="%1$s-error"%4$s%5$s />',
            esc_attr($id),
            esc_attr($field->name),
            esc_attr(trim($class . ' ' . $field->cssClass)),
            $this->requiredAttr($field),
            $this->extraAttrs($field),
        );
    }

    /**
     * A radio or checkbox group: a fieldset whose legend is the label, with one input
     * per option. Checkbox groups submit an array (`name[]`).
     */
    private function group(string $id, FieldSchema $field, string $error): string
    {
        $isCheckbox = $field->type === 'checkbox-group';
        $inputType = $isCheckbox ? 'checkbox' : 'radio';
        $nameAttr = $isCheckbox ? $field->name . '[]' : $field->name;

        $items = '';
        $i = 0;

        foreach ($field->options as $value => $label) {
            $optionId = $id . '-' . $i++;
            $items .= sprintf(
                '<span class="corex-form__option"><input id="%1$s" name="%2$s" type="%3$s" value="%4$s" class="corex-form__choice"%5$s />'
                . '<label for="%1$s" class="corex-form__option-label">%6$s</label></span>',
                esc_attr($optionId),
                esc_attr($nameAttr),
                esc_attr($inputType),
                esc_attr($value),
                $this->requiredAttr($field),
                esc_html($label),
            );
        }

        return sprintf(
            '<fieldset class="%1$s" data-corex-field="%2$s" aria-describedby="%3$s-error">'
            . '<legend class="%4$s">%5$s%6$s</legend>%7$s%8$s</fieldset>',
            esc_attr($this->wrapperClass($field)),
            esc_attr($field->name),
            esc_attr($id),
            esc_attr($this->labelClass($field)),
            esc_html($field->label),
            $this->requiredMarker($field),
            $items,
            $error,
        );
    }

    private function label(string $id, FieldSchema $field): string
    {
        // A single checkbox/toggle reads better with the label after the control.
        return sprintf(
            '<label for="%1$s" class="%2$s">%3$s%4$s</label>',
            esc_attr($id),
            esc_attr($this->labelClass($field)),
            esc_html($field->label),
            $this->requiredMarker($field),
        );
    }

    private function requiredMarker(FieldSchema $field): string
    {
        return $field->required
            ? ' <span class="corex-form__required" aria-hidden="true">*</span>'
            : '';
    }

    private function requiredAttr(FieldSchema $field): string
    {
        return $field->required ? ' required aria-required="true"' : '';
    }

    private function extraAttrs(FieldSchema $field): string
    {
        $out = '';

        foreach ($field->attrs as $key => $value) {
            $out .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
        }

        return $out;
    }

    private function wrapperClass(FieldSchema $field): string
    {
        return trim('corex-form__field corex-form__field--' . $field->width);
    }

    private function controlClass(FieldSchema $field): string
    {
        return trim('corex-form__input ' . $field->cssClass);
    }

    private function labelClass(FieldSchema $field): string
    {
        return trim('corex-form__label corex-form__label--' . $field->labelMode);
    }
}
