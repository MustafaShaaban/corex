<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Forms\Schema\FieldSchema;
use InvalidArgumentException;

/**
 * The parts of one form, for a site that writes the form's markup itself (spec 104, US3).
 *
 * A form's `markup()` is handed this and returns HTML built from it. The stock form is built
 * from the same parts, so a form drawn by hand validates, submits, shows its errors and is
 * challenged as the stock one is, and keeps doing so when CoreX changes.
 *
 * A form cannot work without {@see self::attributes()} on its `<form>`, {@see self::hidden()}
 * inside it, and {@see self::status()} inside it; nor, when it shows a challenge, without
 * {@see self::challenge()}. Every field needs a wrapper carrying
 * {@see self::fieldAttributes()} that holds its control and its {@see self::error()}.
 *
 * Every method returns markup or attributes that are already escaped.
 */
final readonly class FormParts
{
    /**
     * @param array<string,string>      $attributes What the `<form>` element carries, name to value, unescaped.
     * @param array<string,FieldSchema> $schema     The form's fields, by name.
     * @param string                    $hidden     The trap field, and the token field of a protected form.
     */
    public function __construct(
        private string $slug,
        private array $attributes,
        private array $schema,
        private string $hidden,
        private string $submitLabel,
        private FieldRenderer $fields,
        private string $challenge = '',
    ) {
    }

    /**
     * Which of the parts this form cannot work without are absent from its own markup, named as
     * the methods that supply them.
     *
     * @return list<string>
     */
    public function missingFrom(string $markup): array
    {
        $required = [
            'attributes()' => 'data-corex-endpoint="',
            'hidden()'     => 'class="corex-form__hp"',
            'status()'     => 'corex-form__status',
        ];
        if (str_contains($this->hidden, 'corex-form__captcha-token')) {
            // The token field is the last thing hidden() prints, so its presence shows the whole part is there.
            $required['hidden()'] = 'class="corex-form__captcha-token"';
        }
        if ($this->challenge !== '') {
            $required['challenge()'] = 'class="corex-form__challenge"';
        }

        return array_keys(array_filter(
            $required,
            static fn (string $mark): bool => ! str_contains($markup, $mark),
        ));
    }

    /**
     * The attributes of the `<form>` element: the class the runtime binds, where and how it
     * submits, its schema, its messages and its wording.
     *
     * @param array<string,string> $extra Attributes of the site's own. A `class` is added to CoreX's; anything
     *                                    CoreX sets cannot be replaced.
     */
    public function attributes(array $extra = []): string
    {
        $attributes = $this->attributes;
        $class      = trim((string) ($extra['class'] ?? ''));
        unset($extra['class']);

        if ($class !== '') {
            $attributes['class'] .= ' ' . $class;
        }

        return self::attributeString($attributes + $extra);
    }

    /**
     * The fields nobody sees: the trap field, and the token field when the form is protected.
     */
    public function hidden(): string
    {
        return $this->hidden;
    }

    /**
     * Where the provider's challenge is shown, for a protected form on a site whose provider
     * shows one (Turnstile, hCaptcha). Empty otherwise, so it is always safe to print. Put it
     * before the submit button.
     */
    public function challenge(): string
    {
        return $this->challenge;
    }

    /**
     * One field as the stock form draws it: wrapper, label, control, help text and error place.
     */
    public function field(string $name): string
    {
        return $this->fields->render($this->slug, $this->schemaOf($name));
    }

    /**
     * What the element wrapping a field carries, so the runtime can find the field's error place.
     */
    public function fieldAttributes(string $name): string
    {
        $field = $this->schemaOf($name);

        return self::attributeString([
            'data-corex-field'      => $field->name,
            'data-corex-visibility' => $field->visibility,
        ]);
    }

    /**
     * A field's label, tied to its control.
     */
    public function label(string $name): string
    {
        return $this->fields->labelFor($this->slug, $this->schemaOf($name));
    }

    /**
     * The attributes of a control written by hand: its id and name, what describes it, and
     * whether it is required. The site adds the element, its `type` and its classes.
     *
     * @param array<string,string> $extra Attributes of the site's own; what CoreX sets cannot be replaced.
     */
    public function control(string $name, array $extra = []): string
    {
        return self::attributeString($this->fields->controlAttributes($this->slug, $this->schemaOf($name)) + $extra);
    }

    /**
     * Where a field's error is written, and announced.
     */
    public function error(string $name): string
    {
        return $this->fields->errorPlace($this->slug, $this->schemaOf($name));
    }

    /**
     * The submit button, with the form's label.
     *
     * @param array<string,string> $extra Attributes of the site's own. A `class` is added to CoreX's.
     */
    public function submit(array $extra = []): string
    {
        $class = trim('corex-form__submit ' . trim((string) ($extra['class'] ?? '')));
        unset($extra['class'], $extra['type']);

        return sprintf(
            '<button %s>%s</button>',
            self::attributeString(['type' => 'submit', 'class' => $class] + $extra),
            esc_html($this->submitLabel),
        );
    }

    /**
     * Where the confirmation and the general error are announced.
     */
    public function status(): string
    {
        return '<p class="corex-form__status" role="status" aria-live="polite"></p>';
    }

    private function schemaOf(string $name): FieldSchema
    {
        return $this->schema[$name]
            ?? throw new InvalidArgumentException(sprintf('The form "%s" has no field named "%s".', $this->slug, $name));
    }

    /**
     * @param array<string,string> $attributes
     */
    private static function attributeString(array $attributes): string
    {
        $pairs = [];

        foreach ($attributes as $name => $value) {
            // A bare attribute, as `novalidate` and `required` are written.
            $pairs[] = $value === $name || $value === ''
                ? esc_attr($name)
                : sprintf('%s="%s"', esc_attr($name), esc_attr($value));
        }

        return implode(' ', $pairs);
    }
}
