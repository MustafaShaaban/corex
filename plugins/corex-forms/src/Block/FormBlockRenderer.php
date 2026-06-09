<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;
use Corex\Forms\FormRegistry;
use Corex\Forms\Schema\FieldSchema;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Submission\FormSubmissionService;

/**
 * Server-renders a registered form from its schema: accessible (label-bound inputs,
 * required markers, an aria-live status), translation-ready, RTL-aware (logical CSS,
 * applied via the block's stylesheet — no inline styles), and carrying the REST nonce
 * + honeypot the secured endpoint expects. Unknown slug → empty output (non-fatal).
 */
final class FormBlockRenderer implements BlockRenderer
{
    public function __construct(
        private readonly FormRegistry $forms,
        private readonly SchemaResolver $resolver,
    ) {
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $slug = isset($attributes['formSlug']) ? sanitize_key((string) $attributes['formSlug']) : '';
        $form = $this->forms->find($slug);

        if ($form === null) {
            return '';
        }

        $fields = '';

        foreach ($this->resolver->resolve($form->fields()) as $field) {
            $fields .= $this->field($slug, $field);
        }

        return sprintf(
            '<form class="corex-form" method="post" data-corex-form="%1$s" data-corex-endpoint="%2$s"'
            . ' data-corex-nonce="%3$s" data-corex-success="%4$s" data-corex-error="%5$s">'
            . '%6$s'
            . '<input type="text" name="%7$s" class="corex-form__hp" tabindex="-1" autocomplete="off" aria-hidden="true" value="" />'
            . '<button type="submit" class="corex-form__submit">%8$s</button>'
            . '<p class="corex-form__status" role="status" aria-live="polite"></p>'
            . '</form>',
            esc_attr($slug),
            esc_url(rest_url('corex/v1/forms/' . $slug)),
            esc_attr(wp_create_nonce('wp_rest')),
            esc_attr__('Thank you — your message has been sent.', 'corex'),
            esc_attr__('Please review the highlighted fields and try again.', 'corex'),
            $fields,
            esc_attr(FormSubmissionService::HONEYPOT_KEY),
            esc_html__('Send', 'corex'),
        );
    }

    private function field(string $slug, FieldSchema $field): string
    {
        $id = 'corex-' . $slug . '-' . $field->name;

        $marker = $field->required
            ? ' <span class="corex-form__required" aria-hidden="true">*</span>'
            : '';

        $label = sprintf(
            '<label for="%1$s" class="corex-form__label">%2$s%3$s</label>',
            esc_attr($id),
            esc_html($field->label),
            $marker,
        );

        return sprintf('<div class="corex-form__field">%s%s</div>', $label, $this->control($id, $field));
    }

    private function control(string $id, FieldSchema $field): string
    {
        $required = $field->required ? 'required aria-required="true"' : '';

        if ($field->type === 'textarea') {
            return sprintf(
                '<textarea id="%1$s" name="%2$s" class="corex-form__input" %3$s></textarea>',
                esc_attr($id),
                esc_attr($field->name),
                $required,
            );
        }

        return sprintf(
            '<input id="%1$s" name="%2$s" type="%3$s" class="corex-form__input" %4$s />',
            esc_attr($id),
            esc_attr($field->name),
            esc_attr($field->type === 'email' ? 'email' : 'text'),
            $required,
        );
    }
}
