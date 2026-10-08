<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Schema\FieldSchema;
use Corex\Forms\Schema\SchemaExporter;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Submission\CaptchaAction;
use Corex\Forms\Submission\CodeFormProtection;
use Corex\Forms\Submission\FormChallengeContextFactory;
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
        private readonly ?FormChallengeContextFactory $challenge = null,
        private readonly ?ProtectedFormRegistry $protectedForms = null,
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
        $token  = $this->challengeField($form);
        $parts  = $this->partsOf($form, $schema, $token);
        $own    = $form->markup($parts);

        return $own === null ? $this->stockForm($parts, $schema) : $this->ownMarkup($form, $parts, $own);
    }

    /**
     * @param array<string,FieldSchema> $schema
     * @param string                    $token  The token field of a protected form; empty for any other.
     */
    private function partsOf(Form $form, array $schema, string $token): FormParts
    {
        return new FormParts(
            $form->slug,
            [
                'class'               => 'corex-form',
                'method'              => 'post',
                'novalidate'          => 'novalidate',
                'enctype'             => 'multipart/form-data',
                'data-corex-form'     => $form->slug,
                'data-corex-endpoint' => esc_url(rest_url('corex/v1/forms/' . $form->slug)),
                'data-corex-nonce'    => wp_create_nonce('wp_rest'),
                'data-corex-success'  => self::stated($form->successMessage(), __('Thank you — your message has been sent.', 'corex')),
                'data-corex-error'    => self::stated($form->errorMessage(), __('Please review the highlighted fields and try again.', 'corex')),
                'data-corex-schema'   => (string) wp_json_encode($this->exporter->toArray($schema)),
                'data-corex-messages' => ValidationMessages::toAttribute(),
            ],
            $schema,
            sprintf(
                '<input type="text" name="%s" class="corex-form__hp" tabindex="-1" autocomplete="off" aria-hidden="true" value="" />',
                esc_attr(FormSubmissionService::HONEYPOT_KEY),
            ) . $token,
            self::stated($form->submitLabel(), __('Send', 'corex')),
            $this->fieldRenderer,
            $token === '' || $this->challenge === null
                ? ''
                : ChallengeTokenField::widgetPlace($this->challenge->widgetProvider(), $this->challenge->siteKey()),
        );
    }

    /**
     * The form CoreX draws for a form that draws none of its own.
     *
     * @param array<string,FieldSchema> $schema
     */
    private function stockForm(FormParts $parts, array $schema): string
    {
        $fields = '';

        foreach (array_keys($schema) as $name) {
            $fields .= $parts->field($name);
        }

        return '<form ' . $parts->attributes() . '>' . $fields . $parts->hidden() . $parts->challenge()
            . $parts->submit() . $parts->status() . '</form>';
    }

    /**
     * A form's own markup, when it holds what the form cannot work without (spec 104, FR-024).
     *
     * Otherwise nobody is handed a form that would fail silently: a visitor gets nothing, and
     * somebody who can edit the page is told which part is missing.
     */
    private function ownMarkup(Form $form, FormParts $parts, string $markup): string
    {
        $missing = $parts->missingFrom($markup);

        if ($missing === []) {
            return $markup;
        }

        _doing_it_wrong(
            esc_html($form::class . '::markup'),
            esc_html(sprintf('The markup of the form "%s" is missing: %s.', $form->slug, implode(', ', $missing))),
            '0.44.0',
        );

        if (! current_user_can('edit_posts')) {
            return '';
        }

        return sprintf(
            '<p class="corex-form__notice" role="alert">%s</p>',
            esc_html(sprintf(
                /* translators: 1: the form's name, 2: a list of method names. */
                __('The form "%1$s" is not shown to visitors: its markup is missing %2$s.', 'corex'),
                $form->label(),
                implode(', ', $missing),
            )),
        );
    }

    /**
     * The token field of a form that asked to be protected, on a site with a provider to ask.
     * Declaring the form is what loads the provider's script on this page, and on no other
     * (spec 104, FR-011).
     */
    private function challengeField(Form $form): string
    {
        $protection = CodeFormProtection::of($form);

        if ($this->challenge === null || $this->protectedForms === null || ! $this->challenge->isProtected($protection)) {
            return '';
        }

        $action = CaptchaAction::forFlow($form->slug, isset($protection['action']) ? (string) $protection['action'] : null);
        $this->protectedForms->declare($form->slug, $action);

        return ChallengeTokenField::render($action);
    }

    /**
     * The wording a form states, or CoreX's own when it states none (spec 104, FR-002, FR-004).
     */
    private static function stated(string $wording, string $stock): string
    {
        return trim($wording) === '' ? $stock : $wording;
    }
}
