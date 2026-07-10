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
    private const INPUT_TYPES = ['text', 'email', 'number', 'tel', 'url', 'password', 'date', 'time', 'file', 'hidden'];

    public function render(string $slug, FieldSchema $field): string
    {
        $id = 'corex-' . $slug . '-' . $field->name;
        $error = sprintf('<span class="corex-form__error" id="%s-error" role="alert"></span>', esc_attr($id));

        if ($field->type === 'step') {
            return sprintf(
                '<div class="corex-form__step" data-corex-step="%1$s"><h3 class="corex-form__step-title">%2$s</h3>%3$s</div>',
                esc_attr($field->stepKey !== '' ? $field->stepKey : $field->name),
                esc_html($field->label),
                $this->help($id, $field),
            );
        }

        if ($field->type === 'hidden') {
            return $this->input($id, $field);
        }

        if ($field->isChoice() && ! in_array($field->type, ['select', 'multi-select'], true)) {
            return $this->group($id, $field, $error);
        }

        return sprintf(
            '<div class="%1$s" data-corex-field="%2$s" data-corex-visibility="%3$s">%4$s%5$s%6$s%7$s</div>',
            esc_attr($this->wrapperClass($field)),
            esc_attr($field->name),
            esc_attr($field->visibility),
            $this->label($id, $field),
            $this->control($id, $field),
            $this->help($id, $field),
            $error,
        );
    }

    private function control(string $id, FieldSchema $field): string
    {
        return match ($field->type) {
            'textarea'       => $this->textarea($id, $field),
            'select', 'multi-select' => $this->select($id, $field),
            'checkbox', 'consent', 'toggle' => $this->checkbox($id, $field),
            default          => $this->input($id, $field),
        };
    }

    private function input(string $id, FieldSchema $field): string
    {
        $type = match ($field->type) {
            'phone' => 'tel',
            'rating' => 'number',
            default => in_array($field->type, self::INPUT_TYPES, true) ? $field->type : 'text',
        };

        return sprintf(
            '<input id="%1$s" name="%2$s" type="%3$s" class="%4$s" aria-describedby="%5$s"%6$s%7$s%8$s%9$s />',
            esc_attr($id),
            esc_attr($field->name),
            esc_attr($type),
            esc_attr($this->controlClass($field)),
            esc_attr($this->describedBy($id, $field)),
            $this->requiredAttr($field),
            $this->placeholderAttr($field),
            $this->valueAttr($field),
            $this->extraAttrs($field),
        );
    }

    private function textarea(string $id, FieldSchema $field): string
    {
        return sprintf(
            '<textarea id="%1$s" name="%2$s" class="%3$s" aria-describedby="%4$s"%5$s%6$s%7$s>%8$s</textarea>',
            esc_attr($id),
            esc_attr($field->name),
            esc_attr($this->controlClass($field)),
            esc_attr($this->describedBy($id, $field)),
            $this->requiredAttr($field),
            $this->placeholderAttr($field),
            $this->extraAttrs($field),
            esc_html(is_scalar($field->defaultValue) ? (string) $field->defaultValue : ''),
        );
    }

    private function select(string $id, FieldSchema $field): string
    {
        $options = '';

        foreach ($field->options as $value => $label) {
            $options .= sprintf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr($value),
                $this->selectedAttr($this->isSelected($field, (string) $value)),
                esc_html($label),
            );
        }

        $multiple = $field->type === 'multi-select';

        return sprintf(
            '<select id="%1$s" name="%2$s" class="%3$s" aria-describedby="%4$s"%5$s%6$s%7$s>%8$s</select>',
            esc_attr($id),
            esc_attr($field->name . ($multiple ? '[]' : '')),
            esc_attr($this->controlClass($field)),
            esc_attr($this->describedBy($id, $field)),
            $this->requiredAttr($field),
            $multiple ? ' multiple' : '',
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
            '<input id="%1$s" name="%2$s" type="checkbox" value="1" class="%3$s" aria-describedby="%4$s"%5$s%6$s%7$s />',
            esc_attr($id),
            esc_attr($field->name),
            esc_attr(trim($class . ' ' . $field->cssClass)),
            esc_attr($this->describedBy($id, $field)),
            $this->requiredAttr($field),
            $this->checkedAttr((bool) $field->defaultValue),
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
                '<span class="corex-form__option"><input id="%1$s" name="%2$s" type="%3$s" value="%4$s" class="corex-form__choice"%5$s%6$s />'
                . '<label for="%1$s" class="corex-form__option-label">%7$s</label></span>',
                esc_attr($optionId),
                esc_attr($nameAttr),
                esc_attr($inputType),
                esc_attr($value),
                $this->requiredAttr($field),
                $this->checkedAttr($this->isSelected($field, (string) $value)),
                esc_html($label),
            );
        }

        return sprintf(
            '<fieldset class="%1$s" data-corex-field="%2$s" data-corex-visibility="%3$s" aria-describedby="%4$s">'
            . '<legend class="%5$s">%6$s%7$s</legend>%8$s%9$s%10$s</fieldset>',
            esc_attr($this->wrapperClass($field)),
            esc_attr($field->name),
            esc_attr($field->visibility),
            esc_attr($this->describedBy($id, $field)),
            esc_attr($this->labelClass($field)),
            esc_html($field->label),
            $this->requiredMarker($field),
            $items,
            $this->help($id, $field),
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

    private function describedBy(string $id, FieldSchema $field): string
    {
        return $field->helpText === '' ? $id . '-error' : $id . '-help ' . $id . '-error';
    }

    private function placeholderAttr(FieldSchema $field): string
    {
        return $field->placeholder === '' ? '' : sprintf(' placeholder="%s"', esc_attr($field->placeholder));
    }

    private function valueAttr(FieldSchema $field): string
    {
        return is_scalar($field->defaultValue)
            ? sprintf(' value="%s"', esc_attr((string) $field->defaultValue))
            : '';
    }

    private function help(string $id, FieldSchema $field): string
    {
        return $field->helpText === ''
            ? ''
            : sprintf(
                '<span class="corex-form__help" id="%1$s-help">%2$s</span>',
                esc_attr($id),
                esc_html($field->helpText),
            );
    }

    private function isSelected(FieldSchema $field, string $value): bool
    {
        $defaults = is_array($field->defaultValue) ? $field->defaultValue : [$field->defaultValue];

        return in_array($value, array_map('strval', $defaults), true);
    }

    private function selectedAttr(bool $selected): string
    {
        return $selected ? ' selected' : '';
    }

    private function checkedAttr(bool $checked): string
    {
        return $checked ? ' checked' : '';
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
