<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowNotice;
use Corex\Forms\Flow\FlowNoticeText;
use Corex\Forms\Flow\FlowProtection;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Flow\FlowStatus;
use Corex\Forms\Flow\FlowStatusResolver;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Schema\SchemaExporter;
use Corex\Forms\Submission\CaptchaAction;
use Corex\Forms\Submission\FlowSchemaFactory;
use Corex\Forms\Submission\FormChallengeContextFactory;
use Corex\Forms\Submission\FlowVisitorSubmissionService;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\PageReference;
use DateTimeImmutable;

/**
 * Renders every persisted-flow block presentation from the same published snapshot.
 *
 * A flow that is not taking answers is not left out (spec 122, US-VIS-01): a closed, expired or
 * not-yet-open flow shows its heading and a notice with the owner's alternative, and a draft or
 * missing flow shows nothing to visitors and a note to signed-in editors.
 */
final readonly class FlowBlockRenderer implements BlockRenderer
{
    private const VARIANTS = ['flow', 'form', 'success-message', 'subscribe', 'survey', 'cta-flow'];

    public function __construct(
        private FlowRepository $flows,
        private FlowSchemaFactory $schemas,
        private SchemaExporter $exporter,
        private FieldRenderer $fields,
        private FormChallengeContextFactory $challenge,
        private ProtectedFormRegistry $protectedForms,
        private FlowStatusResolver $statuses,
        private FlowNoticeText $notices,
        private PageReference $pages,
    ) {
    }

    /** @param array<string,mixed> $attributes */
    public function render(array $attributes, string $content, object $block): string
    {
        $flow = $this->resolveFlow($attributes);
        $status = $flow === null ? null : $this->statuses->at($flow, new DateTimeImmutable('now'));
        $version = $flow === null || $flow->publishedVersion < 1 ? null : $this->flows->findVersion($flow->id, $flow->publishedVersion);
        if ($flow === null || $status === null || $status->state === FlowStatus::DRAFT || $version === null) {
            return $this->editorNote($flow);
        }

        $variant = $this->variant($attributes);
        $notice = $this->notices->notice($flow, $status);
        if ($notice instanceof FlowNotice) {
            return $variant === 'success-message' ? '' : $this->unavailable($flow, $notice, $variant, $attributes);
        }
        if ($variant === 'success-message') {
            return $this->successMessage($flow, $version);
        }

        wp_enqueue_script('corex-runtime');
        wp_enqueue_style('corex-runtime');

        return $this->flowForm($flow, $version, $variant, $attributes, $this->pageId($block));
    }

    /**
     * What stands where a closed, expired or not-yet-open flow's form would be: its heading and a
     * titled notice, with the owner's alternative. Read once by a screen reader: it is no live
     * region, the page says it as it loads.
     *
     * @param array<string,mixed> $attributes
     */
    private function unavailable(Flow $flow, FlowNotice $notice, string $variant, array $attributes): string
    {
        $heading = trim((string) ($attributes['heading'] ?? ''));
        $link = $notice->link();

        return sprintf(
            '<section class="corex-flow corex-flow--%1$s corex-flow--unavailable" data-corex-flow-name="%2$s">%3$s'
            . '<div class="corex-flow-notice"><p class="corex-flow-notice__title">%4$s</p>'
            . '<p class="corex-flow-notice__message">%5$s</p>%6$s</div>%7$s</section>',
            esc_attr($variant),
            esc_attr($flow->name),
            $this->heading($heading !== '' ? $heading : $flow->name),
            esc_html($notice->title),
            esc_html($notice->message),
            $link['url'] === '' ? '' : sprintf(
                '<p class="corex-flow-notice__alternative"><a href="%1$s">%2$s</a></p>',
                esc_url($link['url'], ['http', 'https', 'mailto']),
                esc_html($link['label']),
            ),
            $this->editorsSee(sprintf(
                /* translators: %s: the name of a form built in Forms & Flows. */
                __('Only editors see this: the form "%s" is not taking answers. Reopen it or change its dates in Forms & Flows.', 'corex'),
                $flow->name,
            ), $flow),
        );
    }

    /** Nothing for a visitor; for a signed-in editor, a note that the block's form is not published. */
    private function editorNote(?Flow $flow): string
    {
        $note = $this->editorsSee(__('Only editors see this: the form in this block is not published.', 'corex'), $flow);

        return $note === '' ? '' : sprintf('<section class="corex-flow corex-flow--unavailable">%s</section>', $note);
    }

    /** A note only someone who can edit pages reads, with a link to the flow for whoever may open it. */
    private function editorsSee(string $text, ?Flow $flow): string
    {
        if (! is_user_logged_in() || ! current_user_can('edit_posts')) {
            return '';
        }
        $link = '';
        if (current_user_can('corex_manage_forms') || current_user_can('manage_options')) {
            $address = admin_url('admin.php?page=corex-forms' . ($flow === null ? '' : '&corex_flow=' . $flow->id));
            $link = sprintf(' <a href="%1$s">%2$s</a>', esc_url($address), esc_html__('Open it in Forms & Flows', 'corex'));
        }

        return sprintf('<p class="corex-flow-notice corex-flow-notice--editors">%1$s%2$s</p>', esc_html($text), $link);
    }

    /** The page the block is printed on, for "Page owner" routing. */
    private function pageId(object $block): int
    {
        $context = property_exists($block, 'context') && is_array($block->context) ? $block->context : [];
        $pageId = (int) ($context['postId'] ?? 0);

        return $pageId > 0 ? $pageId : (int) get_queried_object_id();
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
    private function flowForm(Flow $flow, FlowVersion $version, string $variant, array $attributes, int $pageId): string
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
            . '<form class="corex-form" method="post" novalidate enctype="multipart/form-data"'
            . ' data-corex-form="%5$s" data-corex-flow="%6$d"'
            . ' data-corex-flow-version="%7$d" data-corex-endpoint="%8$s" data-corex-nonce="%9$s"'
            . ' data-corex-success="%10$s" data-corex-success-config="%11$s" data-corex-error="%12$s" data-corex-schema="%13$s"'
            . ' data-corex-messages="%18$s">'
            . '%14$s<input type="text" name="%15$s" class="corex-form__hp" tabindex="-1" autocomplete="off" aria-hidden="true" value="" />'
            . '<input type="hidden" name="%19$s" value="%7$d" />%20$s'
            . '%16$s'
            . '<button type="submit" class="corex-form__submit">%17$s</button>'
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
            $this->captchaField($flow, $version),
            esc_html($submitLabel !== '' ? $submitLabel : $this->defaultSubmitLabel($variant)),
            esc_attr(ValidationMessages::toAttribute()),
            esc_attr(FlowVisitorSubmissionService::VERSION_FIELD),
            $pageId > 0 ? sprintf(
                '<input type="hidden" name="%1$s" value="%2$s" />',
                esc_attr(PageReference::FIELD),
                esc_attr($this->pages->sign($pageId, $flow->id)),
            ) : '',
        );
    }

    /**
     * The captcha token field, rendered only when this form is captcha-protected and a provider
     * is configured. Declaring the form into the registry is what triggers the footer script
     * enqueue — a page with no protected form therefore loads no provider script at all (FR-001).
     * The field is left empty; the client script fills it with a fresh token at submit time.
     */
    private function captchaField(Flow $flow, FlowVersion $version): string
    {
        $protection = FlowProtection::normalize($version->configuration->protection);
        if (! $this->challenge->isProtected($protection)) {
            return '';
        }

        $action = CaptchaAction::forFlow(
            $flow->slug,
            isset($protection['action']) ? (string) $protection['action'] : null,
        );
        $this->protectedForms->declare($flow->slug, $action);

        return ChallengeTokenField::render($action)
            . ChallengeTokenField::widgetPlace($this->challenge->widgetProvider(), $this->challenge->siteKey());
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
