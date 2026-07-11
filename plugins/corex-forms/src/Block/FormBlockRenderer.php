<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;
use Corex\Forms\FormRegistry;
use Corex\Forms\Schema\SchemaExporter;
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
        private readonly SchemaExporter $exporter,
        private readonly FieldRenderer $fieldRenderer,
        private readonly ?FlowBlockRenderer $flowRenderer = null,
    ) {
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        // block.json declares `flowId`/`flowSlug` defaults (0 / ""), so `isset()` is always true
        // once WordPress merges those defaults in — which would route every block, including a
        // legacy `formSlug` form, to the flow renderer and produce empty output. Route to the flow
        // renderer only when a flow is actually referenced; otherwise fall through to the form path.
        $referencesFlow = ((int) ($attributes['flowId'] ?? 0)) > 0
            || trim((string) ($attributes['flowSlug'] ?? '')) !== '';
        if ($this->flowRenderer !== null && $referencesFlow) {
            return $this->flowRenderer->render(
                [...$attributes, 'variant' => 'form'],
                $content,
                $block,
            );
        }

        $slug = isset($attributes['formSlug']) ? sanitize_key((string) $attributes['formSlug']) : '';
        $form = $this->forms->find($slug);

        if ($form === null) {
            return '';
        }

        // Conditional load (Principle VI): the shared runtime + its styles enqueue only
        // here, where a form actually renders — never globally. The runtime drives the
        // submit lifecycle and auto-binds this form (spec 043).
        wp_enqueue_script('corex-runtime');
        wp_enqueue_style('corex-runtime');

        // Resolve the schema once: it both renders the fields and is exported to the
        // client so JS validates against the SAME definition the server enforces.
        $schema = $this->resolver->resolve($form->fields());

        $fields = '';

        foreach ($schema as $field) {
            $fields .= $this->fieldRenderer->render($slug, $field);
        }

        return sprintf(
            '<form class="corex-form" method="post" data-corex-form="%1$s" data-corex-endpoint="%2$s"'
            . ' data-corex-nonce="%3$s" data-corex-success="%4$s" data-corex-error="%5$s" data-corex-schema="%6$s">'
            . '%7$s'
            . '<input type="text" name="%8$s" class="corex-form__hp" tabindex="-1" autocomplete="off" aria-hidden="true" value="" />'
            . '<button type="submit" class="corex-form__submit">%9$s</button>'
            . '<p class="corex-form__status" role="status" aria-live="polite"></p>'
            . '</form>',
            esc_attr($slug),
            esc_url(rest_url('corex/v1/forms/' . $slug)),
            esc_attr(wp_create_nonce('wp_rest')),
            esc_attr__('Thank you — your message has been sent.', 'corex'),
            esc_attr__('Please review the highlighted fields and try again.', 'corex'),
            esc_attr((string) wp_json_encode($this->exporter->toArray($schema))),
            $fields,
            esc_attr(FormSubmissionService::HONEYPOT_KEY),
            esc_html__('Send', 'corex'),
        );
    }
}
