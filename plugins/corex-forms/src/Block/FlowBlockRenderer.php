<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Schema\SchemaExporter;
use Corex\Forms\Submission\FlowSchemaFactory;
use Corex\Forms\Submission\FormSubmissionService;

/**
 * Renders every persisted-flow block presentation from the same published snapshot.
 */
final readonly class FlowBlockRenderer implements BlockRenderer
{
    private const VARIANTS = ['flow', 'form', 'success-message', 'subscribe', 'survey', 'cta-flow'];

    public function __construct(
        private FlowRepository $flows,
        private FlowSchemaFactory $schemas,
        private SchemaExporter $exporter,
        private FieldRenderer $fields,
    ) {
    }

    /** @param array<string,mixed> $attributes */
    public function render(array $attributes, string $content, object $block): string
    {
        $flow = $this->resolveFlow($attributes);
        if ($flow === null || $flow->state !== Flow::STATE_PUBLISHED || $flow->publishedVersion < 1) {
            return '';
        }
        $version = $this->flows->findVersion($flow->id, $flow->publishedVersion);
        if ($version === null) {
            return '';
        }

        $variant = $this->variant($attributes);
        if ($variant === 'success-message') {
            return $this->successMessage($flow, $version);
        }

        wp_enqueue_script('corex-runtime');
        wp_enqueue_style('corex-runtime');

        return $this->flowForm($flow, $version, $variant, $attributes);
    }

    /** @param array<string,mixed> $attributes */
    private function resolveFlow(array $attributes): ?Flow
    {
        $flowId = absint($attributes['flowId'] ?? 0);
        if ($flowId > 0) {
            return $this->flows->find($flowId);
        }
        $slug = sanitize_key((string) ($attributes['flowSlug'] ?? ''));

        return $slug === '' ? null : $this->flows->findBySlug($slug);
    }

    /** @param array<string,mixed> $attributes */
    private function variant(array $attributes): string
    {
        $variant = sanitize_key((string) ($attributes['variant'] ?? 'flow'));

        return in_array($variant, self::VARIANTS, true) ? $variant : 'flow';
    }

    /** @param array<string,mixed> $attributes */
    private function flowForm(Flow $flow, FlowVersion $version, string $variant, array $attributes): string
    {
        $schema = $this->schemas->make($version->configuration);
        $fields = '';
        foreach ($schema as $field) {
            $fields .= $this->fields->render($flow->slug, $field);
        }
        $heading = trim((string) ($attributes['heading'] ?? ''));
        $intro = trim((string) ($attributes['intro'] ?? ''));
        $submitLabel = trim((string) ($attributes['submitLabel'] ?? ''));
        $success = $this->successConfig($version->configuration->success);

        return sprintf(
            '<section class="corex-flow corex-flow--%1$s" data-corex-flow-name="%2$s">%3$s%4$s'
            . '<form class="corex-form" method="post" data-corex-form="%5$s" data-corex-flow="%6$d"'
            . ' data-corex-flow-version="%7$d" data-corex-endpoint="%8$s" data-corex-nonce="%9$s"'
            . ' data-corex-success="%10$s" data-corex-success-config="%11$s" data-corex-error="%12$s" data-corex-schema="%13$s">'
            . '%14$s<input type="text" name="%15$s" class="corex-form__hp" tabindex="-1" autocomplete="off" aria-hidden="true" value="" />'
            . '<button type="submit" class="corex-form__submit">%16$s</button>'
            . '<p class="corex-form__status" role="status" aria-live="polite"></p></form></section>',
            esc_attr($variant),
            esc_attr($flow->name),
            $this->heading($heading !== '' ? $heading : $flow->name),
            $intro === '' ? '' : sprintf('<p class="corex-flow__intro">%s</p>', esc_html($intro)),
            esc_attr($flow->slug),
            $flow->id,
            $version->versionNumber,
            esc_url(rest_url('corex/v1/flows/' . $flow->id . '/submit')),
            esc_attr(wp_create_nonce('wp_rest')),
            esc_attr($this->successText($success)),
            esc_attr((string) wp_json_encode($success)),
            esc_attr__('Please review the highlighted fields and try again.', 'corex'),
            esc_attr((string) wp_json_encode($this->exporter->toArray($schema))),
            $fields,
            esc_attr(FormSubmissionService::HONEYPOT_KEY),
            esc_html($submitLabel !== '' ? $submitLabel : $this->defaultSubmitLabel($variant)),
        );
    }

    private function successMessage(Flow $flow, FlowVersion $version): string
    {
        return sprintf(
            '<div class="corex-flow corex-flow--success-message" data-corex-flow-name="%1$s" role="status">%2$s</div>',
            esc_attr($flow->name),
            esc_html($this->successText($version->configuration->success)),
        );
    }

    private function heading(string $heading): string
    {
        return sprintf('<h2 class="corex-flow__title">%s</h2>', esc_html($heading));
    }

    /** @param array<string,mixed> $success */
    private function successText(array $success): string
    {
        $message = trim((string) ($success['message'] ?? ''));

        return $message !== '' ? $message : __('Thank you. Your submission was received.', 'corex');
    }

    private function defaultSubmitLabel(string $variant): string
    {
        return match ($variant) {
            'subscribe' => __('Subscribe', 'corex'),
            'survey' => __('Submit survey', 'corex'),
            'cta-flow' => __('Continue', 'corex'),
            default => __('Submit', 'corex'),
        };
    }

    /**
     * @param array<string,mixed> $success
     * @return array<string,mixed>
     */
    private function successConfig(array $success): array
    {
        if (($success['type'] ?? '') === 'page' && isset($success['page_id']) && function_exists('get_permalink')) {
            $target = get_permalink((int) $success['page_id']);
            if (is_string($target) && $target !== '') {
                $success['target_url'] = $target;
            }
        }

        return $success;
    }
}
