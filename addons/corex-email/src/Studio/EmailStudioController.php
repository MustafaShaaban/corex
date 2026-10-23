<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Email\Capture\CapturedEmail;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\EmailAttempt;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Delivery\MailOutcome;
use Corex\Email\Design\PlainText;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Routing\EmailRoute;
use Corex\Email\Routing\EmailRouteRepository;
use Corex\Http\ResponseEnvelope;
use Corex\Http\RouteParam;
use Corex\Mail\Capture\MailGate;
use Corex\Mail\SubjectLine;
use Corex\Support\Options\LoadedOptions;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Capability- and nonce-gated REST boundary for the functional Email Studio.
 */
final class EmailStudioController
{
    /** Where the capture-only switch is kept: config key `mail.capture_scope`. */
    public const CAPTURE_SCOPE_OPTION = 'corex_mail_capture_scope';

    /** How many of the newest attempts and captures the screen reads. */
    private const RECENT = 100;

    private readonly EmailTemplateRepository $templates;

    private readonly EmailLayoutRepository $layouts;

    private readonly EmailPartialRepository $partials;

    private readonly EmailRouteRepository $routes;

    private readonly CapturedEmailRepository $captures;

    private readonly EmailAttemptRepository $attempts;

    public function __construct(
        EmailStudioRepositories $repositories,
        private readonly EmailStudioService $studio,
        private readonly EmailTemplateService $editor,
        private readonly EmailDeliveryContextFactory $delivery,
        /** What happens to mail on this site, said on every tab (spec 113). */
        private readonly ?MailOutcome $outcome = null,
        /** The preview and test send drawn by the code that sends (spec 120). */
        private readonly ?EmailTemplatePreview $preview = null,
        /** The templates CoreX ships, which can be reset (spec 120). */
        private readonly ?DefaultEmailTemplates $defaults = null,
        private readonly ?EmailTemplateWarnings $warnings = null,
    ) {
        $this->templates = $repositories->templates;
        $this->layouts   = $repositories->layouts;
        $this->partials  = $repositories->partials;
        $this->routes    = $repositories->routes;
        $this->captures  = $repositories->captures;
        $this->attempts  = $repositories->attempts;
    }

    public function register(): void
    {
        $this->route('/email-studio', 'GET', 'index', 'canManage');
        $this->route('/email-studio/templates', 'POST', 'createTemplate', 'canMutate');
        $this->route('/email-studio/templates/(?P<id>\d+)', 'GET', 'showTemplate', 'canManage');
        $this->route('/email-studio/templates/(?P<id>\d+)/draft', 'POST', 'saveDraft', 'canMutate');
        $this->route('/email-studio/templates/(?P<id>\d+)/activate', 'POST', 'activateTemplate', 'canMutate');
        $this->route('/email-studio/templates/(?P<id>\d+)/health', 'GET', 'health', 'canManage');
        $this->route('/email-studio/templates/(?P<id>\d+)/preview', 'POST', 'previewTemplate', 'canMutate');
        $this->route('/email-studio/templates/(?P<id>\d+)/reset', 'POST', 'resetTemplate', 'canMutate');
        $this->route('/email-studio/layouts', 'POST', 'saveLayout', 'canMutate');
        $this->route('/email-studio/partials', 'POST', 'savePartial', 'canMutate');
        $this->route('/email-studio/routes', 'POST', 'saveRoute', 'canMutate');
        $this->route('/email-studio/test', 'POST', 'testSend', 'canMutate');
        $this->route('/email-studio/attempts/(?P<attempt>[0-9a-f-]+)/resend', 'POST', 'resend', 'canMutate');
        $this->route('/email-studio/capture-scope', 'POST', 'saveCaptureScope', 'canMutate');
    }

    /**
     * The "Capture only CoreX mail" switch (spec 113, FR-007): `all`, the default, or `corex`.
     */
    public function saveCaptureScope(WP_REST_Request $request): WP_REST_Response
    {
        $scope = $request->get_param('scope');
        if (! in_array($scope, [MailGate::SCOPE_ALL, MailGate::SCOPE_COREX], true)) {
            return $this->error('invalid_capture_scope', __('Capture covers all mail or only CoreX mail.', 'corex'), 400);
        }

        LoadedOptions::store(self::CAPTURE_SCOPE_OPTION, $scope);

        return $this->success(['scope' => $scope, 'outcome' => $this->outcome?->describe()]);
    }

    private function route(string $path, string $methods, string $callback, string $permission): void
    {
        register_rest_route('corex/v1', $path, [
            'methods'             => $methods,
            'callback'            => [$this, $callback],
            'permission_callback' => [$this, $permission],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function canMutate(WP_REST_Request $request): bool
    {
        return $this->canManage()
            && wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest') !== false;
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $templates = array_map($this->templateSummary(...), $this->templates->all());
        $layouts   = array_map($this->layoutArray(...), $this->layouts->all());
        $partials  = array_map($this->partialArray(...), $this->partials->all());
        $routes    = array_map($this->routeArray(...), $this->routes->all());
        $captures  = $this->captures->latest(self::RECENT);
        $attempts  = $this->attempts->latest(self::RECENT);
        $states    = array_count_values(array_map(static fn (EmailAttempt $attempt): string => $attempt->state, $attempts));
        $recentTests = array_values(array_filter(
            $attempts,
            static fn (EmailAttempt $attempt): bool => $attempt->source === 'test',
        ));

        return $this->success([
            'outcome' => $this->outcome?->describe(),
            'delivery' => [
                'environment'         => $this->delivery->make()->environment,
                'provider'            => $this->delivery->providerName(),
                'provider_configured' => $this->delivery->providerConfigured(),
                'live_enabled'        => $this->delivery->liveDelivery(),
            ],
            'counts' => [
                'templates' => count($templates),
                'layouts'   => count($layouts),
                'partials'  => count($partials),
                'routes'    => count($routes),
                'captures'  => count($captures),
                'attempts'  => count($attempts),
                'captured'  => (int) ($states[EmailAttempt::STATE_CAPTURED] ?? 0),
                'delivered' => (int) (($states[EmailAttempt::STATE_SENT] ?? 0) + ($states[EmailAttempt::STATE_OPENED] ?? 0)),
                'failed'    => (int) (($states[EmailAttempt::STATE_FAILED] ?? 0) + ($states[EmailAttempt::STATE_REJECTED] ?? 0) + ($states[EmailAttempt::STATE_BOUNCED] ?? 0)),
                // What the captured, delivered and failed counts are counted from (spec 113, FR-022).
                'window'    => self::RECENT,
            ],
            'templates' => $templates,
            'layouts'   => $layouts,
            'partials'  => $partials,
            'routes'    => $routes,
            'captures'  => array_map($this->captureArray(...), $captures),
            'attempts'  => array_map($this->attemptArray(...), $attempts),
            'recent_test_sends' => array_map($this->attemptArray(...), array_slice($recentTests, 0, 10)),
            'health'     => $this->overviewHealth(),
            'variables'  => $this->preview?->variableGroups() ?? [],
        ]);
    }

    public function createTemplate(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $template = $this->templates->create(
                sanitize_key((string) $request->get_param('slug')),
                sanitize_text_field((string) $request->get_param('name')),
                get_current_user_id(),
                new DateTimeImmutable('now'),
            );

            return $this->success(['template' => $this->templateArray($template)], 201);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error('email_template_invalid', $exception->getMessage(), 422);
        }
    }

    public function showTemplate(WP_REST_Request $request): WP_REST_Response
    {
        $template = $this->templates->find(RouteParam::int($request));
        if ($template === null) {
            return $this->error('email_template_not_found', __('That email template was not found.', 'corex'), 404);
        }

        return $this->success([
            'template' => $this->templateArray($template),
            'versions' => array_map($this->versionArray(...), $this->templates->versions($template->id)),
        ]);
    }

    public function saveDraft(WP_REST_Request $request): WP_REST_Response
    {
        $template = $this->templates->find(RouteParam::int($request));
        if ($template === null) {
            return $this->error('email_template_not_found', __('That email template was not found.', 'corex'), 404);
        }

        try {
            $candidate = $this->candidate($template, $request);
            $errors = $this->editor->validateDraft($candidate);
            if ($this->layouts->findVersion($candidate->layoutId, $candidate->layoutVersion) === null) {
                $errors['layout_id'] = __('Choose an available email layout revision.', 'corex');
            }
            if ($errors !== []) {
                return $this->error('email_template_unsafe', __('The draft contains invalid or unsafe content.', 'corex'), 422, ['fields' => $errors]);
            }

            $version = $this->templates->saveDraft($candidate);

            return $this->success([
                'version'  => $this->versionArray($version),
                // Saved, and told: a variable the emails it is bound to do not supply (spec 120).
                'warnings' => $this->warnings?->of($template, $version) ?? [],
            ], 201);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error('email_template_invalid', $exception->getMessage(), 422);
        }
    }

    /**
     * The draft being edited, drawn as it would be sent, with sample values (spec 120, story 1).
     */
    public function previewTemplate(WP_REST_Request $request): WP_REST_Response
    {
        $template = $this->templates->find(RouteParam::int($request));
        if ($template === null || $this->preview === null) {
            return $this->error('email_template_not_found', __('That email template was not found.', 'corex'), 404);
        }

        try {
            return $this->success($this->preview->preview(
                $template,
                $this->candidate($template, $request),
                $this->samples($request->get_param('samples')),
                $this->direction($request->get_param('direction')),
            ));
        } catch (EmailTemplateUnrenderable $exception) {
            return $this->error('email_template_unrenderable', __('This template cannot be drawn yet.', 'corex'), 422, ['fields' => $exception->fields]);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error('email_template_invalid', $exception->getMessage(), 422);
        }
    }

    /** A template CoreX ships, put back as it was shipped (spec 120, story 4). */
    public function resetTemplate(WP_REST_Request $request): WP_REST_Response
    {
        $template = $this->templates->find(RouteParam::int($request));
        if ($template === null || $this->defaults === null || ! $this->defaults->isDefault($template->slug)) {
            return $this->error('email_template_not_default', __('Only an email template CoreX ships can be reset.', 'corex'), 404);
        }

        try {
            $version = $this->defaults->reset($template->id, get_current_user_id(), new DateTimeImmutable('now'));
            $template = $this->templates->find($template->id) ?? $template;

            return $this->success(['template' => $this->templateArray($template), 'version' => $this->versionArray($version)]);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error('email_template_reset_failed', $exception->getMessage(), 422);
        }
    }

    public function activateTemplate(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $template = $this->templates->activate(
                RouteParam::int($request),
                absint($request->get_param('version')),
                get_current_user_id(),
                new DateTimeImmutable('now'),
            );

            return $this->success(['template' => $this->templateArray($template)]);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error('email_template_activation_failed', $exception->getMessage(), 422);
        }
    }

    public function saveRoute(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $templateId = absint($request->get_param('template_id'));
            if ($this->templates->find($templateId) === null) {
                throw new DomainException(__('Choose an available email template.', 'corex'));
            }

            $route = $this->routes->save(new EmailRoute(
                id: 0,
                trigger: strtolower(sanitize_text_field((string) $request->get_param('trigger'))),
                templateId: $templateId,
                recipientRules: $this->rules($request->get_param('recipient_rules')),
                replyToRule: $this->rule($request->get_param('reply_to_rule')),
                enabled: rest_sanitize_boolean($request->get_param('enabled')),
                updatedBy: get_current_user_id(),
                updatedAt: new DateTimeImmutable('now'),
                flowId: absint($request->get_param('flow_id')) ?: null,
                templateVersionPolicy: sanitize_key((string) ($request->get_param('template_version_policy') ?: 'active')),
                priority: $request->get_param('priority') === null
                    ? 100
                    : absint($request->get_param('priority')),
            ));

            return $this->success(['route' => $this->routeArray($route)]);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error('email_route_invalid', $exception->getMessage(), 422);
        }
    }

    public function saveLayout(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $regions = [];
            foreach (['header', 'body', 'button', 'footer'] as $key) {
                $value = (array) $request->get_param('regions');
                $content = is_string($value[$key] ?? null) ? $value[$key] : '';
                $errors = $this->editor->validateFragment($content);
                if ($errors !== []) {
                    return $this->error('email_layout_unsafe', __('The layout contains invalid or unsafe content.', 'corex'), 422, ['fields' => $errors]);
                }
                $regions[$key] = $content;
            }
            $regionInput = (array) $request->get_param('regions');
            $accent = is_string($regionInput['accent'] ?? null) ? trim($regionInput['accent']) : '';
            if ($accent !== '' && preg_match('/^#[0-9a-f]{6}$/i', $accent) !== 1) {
                throw new DomainException(__('Email layout accent must be empty or a six-digit hexadecimal color.', 'corex'));
            }
            $regions['accent'] = $accent;

            $layout = $this->layouts->save(new EmailLayout(
                id: 0,
                slug: sanitize_key((string) $request->get_param('slug')),
                name: sanitize_text_field((string) $request->get_param('name')),
                version: 1,
                regions: $regions,
                dependency: $this->nullableKey($request->get_param('dependency')),
                createdBy: get_current_user_id(),
                createdAt: new DateTimeImmutable('now'),
            ));

            return $this->success(['layout' => $this->layoutArray($layout)], 201);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error('email_layout_invalid', $exception->getMessage(), 422);
        }
    }

    public function savePartial(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $htmlBody  = (string) $request->get_param('html_body');
            $plainText = (string) $request->get_param('plain_text');
            $errors = $this->editor->validateFragment($htmlBody);
            if ($errors !== []) {
                return $this->error('email_partial_unsafe', __('The partial contains invalid or unsafe content.', 'corex'), 422, ['fields' => $errors]);
            }

            $partial = $this->partials->save(new EmailPartial(
                id: 0,
                slug: sanitize_key((string) $request->get_param('slug')),
                name: sanitize_text_field((string) $request->get_param('name')),
                kind: sanitize_key((string) $request->get_param('kind')),
                htmlBody: $htmlBody,
                plainText: $plainText,
                status: sanitize_key((string) $request->get_param('status')),
                version: 1,
                createdBy: get_current_user_id(),
                createdAt: new DateTimeImmutable('now'),
            ));

            return $this->success(['partial' => $this->partialArray($partial)], 201);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error('email_partial_invalid', $exception->getMessage(), 422);
        }
    }

    public function health(WP_REST_Request $request): WP_REST_Response
    {
        $templateId = RouteParam::int($request);
        $number     = absint($request->get_param('version'));
        $template   = $this->templates->find($templateId);
        $version    = $number > 0
            ? $this->templates->findVersion($templateId, $number)
            : ($template === null ? null : $this->templates->findVersion($templateId, $template->draftVersion));
        if ($version === null) {
            return $this->error('email_version_not_found', __('That email template version was not found.', 'corex'), 404);
        }

        $layout = $this->layouts->findVersion($version->layoutId, $version->layoutVersion);

        return $this->success(['health' => $this->studio->health($version, new EmailHealthContext(
            providerConfigured: $this->delivery->providerConfigured(),
            requiresSubscriptionLinks: rest_sanitize_boolean($request->get_param('subscription'))
                || $layout?->slug === 'newsletter',
            replyTo: $this->nullableEmail($request->get_param('reply_to')),
            layout: $layout,
        ))]);
    }

    public function testSend(WP_REST_Request $request): WP_REST_Response
    {
        $templateId = absint($request->get_param('template_id'));
        if ($templateId > 0) {
            return $this->testSendTemplate($templateId, $request);
        }

        try {
            $result = $this->studio->testSend(
                $this->message($request),
                $this->delivery->make(),
                $this->templateReference($request->get_param('template_slug')),
            );

            return $this->success(['result' => $result->toArray()]);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error('email_test_failed', $exception->getMessage(), 422);
        }
    }

    /** The previewed email, sent to one address (spec 120, story 1). */
    private function testSendTemplate(int $templateId, WP_REST_Request $request): WP_REST_Response
    {
        $template = $this->templates->find($templateId);
        if ($template === null || $this->preview === null) {
            return $this->error('email_template_not_found', __('That email template was not found.', 'corex'), 404);
        }
        $to = $request->get_param('to');
        $to = trim((string) (is_array($to) ? ($to[0] ?? '') : $to));

        try {
            $sent = $this->preview->testSend(
                $template,
                $this->candidate($template, $request),
                $to,
                $this->samples($request->get_param('samples')),
            );

            return $this->success([...$sent, 'result' => $sent['result']->toArray()]);
        } catch (EmailTemplateUnrenderable $exception) {
            $code = isset($exception->fields['to']) ? 'email_test_invalid_address' : 'email_template_unrenderable';

            return $this->error($code, $exception->getMessage(), 422, ['fields' => $exception->fields]);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error('email_test_failed', $exception->getMessage(), 422);
        }
    }

    public function resend(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $attemptId = RouteParam::string($request, 'attempt');
            $result = $this->studio->resend(
                $attemptId,
                $this->asTheTemplateSent($attemptId, $this->message($request)),
                $this->delivery->make(),
            );

            return $this->success(['result' => $result->toArray()]);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->error('email_resend_failed', $exception->getMessage(), 422);
        }
    }

    /**
     * The draft a request describes, as a version: what is saved, previewed and test-sent. The
     * variables it declares are the ones its content uses (spec 120).
     */
    private function candidate(EmailTemplate $template, WP_REST_Request $request): EmailTemplateVersion
    {
        $content = [
            'subject'        => (string) $request->get_param('subject'),
            'fromName'       => (string) $request->get_param('from_name'),
            'fromAddress'    => trim((string) $request->get_param('from_address')),
            'htmlBody'       => (string) $request->get_param('html_body'),
            'plainText'      => (string) $request->get_param('plain_text'),
            'plainTextMode'  => sanitize_key((string) $request->get_param('plain_text_mode')) ?: EmailTemplateVersion::PLAIN_AUTO,
            'layoutId'       => absint($request->get_param('layout_id')),
            'layoutVersion'  => absint($request->get_param('layout_version')),
        ];
        $content['variableKeys'] = $this->variableKeys(
            $this->editor->variablesIn($content['subject'], $content['htmlBody'], $content['plainText']),
        );

        return new EmailTemplateVersion(
            id: 1,
            templateId: $template->id,
            versionNumber: max(1, $template->draftVersion + 1),
            subject: $content['subject'],
            fromName: $content['fromName'],
            fromAddress: $content['fromAddress'],
            htmlBody: $content['htmlBody'],
            plainText: $content['plainText'],
            plainTextMode: $content['plainTextMode'],
            layoutId: $content['layoutId'],
            layoutVersion: $content['layoutVersion'],
            variableKeys: $content['variableKeys'],
            createdBy: max(1, get_current_user_id()),
            createdAt: new DateTimeImmutable('now'),
            checksum: hash('sha256', serialize($content)),
        );
    }

    /**
     * Sample values typed beside the preview: text by variable, used for one drawing.
     *
     * @return array<string,string>
     */
    private function samples(mixed $samples): array
    {
        $given = [];
        foreach (is_array($samples) ? $samples : [] as $key => $value) {
            if (is_string($key) && preg_match('/^[a-z][a-z0-9_.]*$/', $key) === 1 && is_scalar($value)) {
                $given[$key] = sanitize_textarea_field((string) $value);
            }
        }

        return $given;
    }

    private function direction(mixed $direction): ?string
    {
        return in_array($direction, ['ltr', 'rtl'], true) ? $direction : null;
    }

    /**
     * A message resent from the log, from the sender of the template its attempt was sent with
     * and with its words as plain text beside them (spec 120, story 2).
     */
    private function asTheTemplateSent(string $attemptId, EmailMessage $message): EmailMessage
    {
        $attempt = $this->attempts->findByAttemptId($attemptId);
        $version = $attempt?->templateId === null || $attempt->templateVersion === null
            ? null
            : $this->templates->findVersion($attempt->templateId, $attempt->templateVersion);

        return new EmailMessage(
            ...[
                ...get_object_vars($message),
                'from'      => $version !== null && $version->fromAddress !== '' ? $version->fromAddress : null,
                'fromName'  => $version !== null && trim($version->fromName) !== '' ? $version->fromName : null,
                'plainText' => PlainText::of($message->body),
            ],
        );
    }

    private function message(WP_REST_Request $request): EmailMessage
    {
        $recipients = [];
        foreach ((array) $request->get_param('to') as $recipient) {
            if (is_string($recipient) && is_email($recipient)) {
                $recipients[] = sanitize_email($recipient);
            }
        }

        return new EmailMessage(
            array_values(array_unique($recipients)),
            [],
            [],
            $this->nullableEmail($request->get_param('reply_to')),
            // As typed, on one line: a subject is a header and not a form's text.
            SubjectLine::of($request->get_param('subject')),
            wp_kses_post((string) $request->get_param('body')),
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    private function templateReference(mixed $value): ?EmailTemplateReference
    {
        $slug = $this->nullableKey($value);
        if ($slug === null) {
            return null;
        }

        $template = $this->templates->findBySlug($slug);
        $version = $template?->activeVersion ?: $template?->draftVersion;

        return new EmailTemplateReference($slug, $template?->id, $version ?: null);
    }

    /** @return list<string> */
    private function variableKeys(mixed $keys): array
    {
        $valid = [];
        foreach (is_array($keys) ? $keys : [] as $key) {
            $key = is_string($key) ? strtolower(trim($key)) : '';
            if (preg_match('/^[a-z][a-z0-9_.]*$/', $key) === 1) {
                $valid[] = $key;
            }
        }

        return array_values(array_unique($valid));
    }

    /** @return list<array{source:string,path?:string,value?:string}> */
    private function rules(mixed $rules): array
    {
        $valid = [];
        foreach (is_array($rules) ? $rules : [] as $rule) {
            $normalized = $this->rule($rule);
            if ($normalized !== null) {
                $valid[] = $normalized;
            }
        }

        return $valid;
    }

    /** @return array{source:string,path?:string,value?:string}|null */
    private function rule(mixed $rule): ?array
    {
        if (! is_array($rule) || ! is_string($rule['source'] ?? null)) {
            return null;
        }

        $normalized = ['source' => sanitize_key($rule['source'])];
        if (is_string($rule['path'] ?? null)) {
            $normalized['path'] = strtolower(trim($rule['path']));
        }
        if (is_string($rule['value'] ?? null)) {
            $normalized['value'] = sanitize_email($rule['value']);
        }

        return $normalized;
    }

    private function nullableEmail(mixed $value): ?string
    {
        return is_string($value) && is_email($value) ? sanitize_email($value) : null;
    }

    private function nullableKey(mixed $value): ?string
    {
        $key = is_string($value) ? sanitize_key($value) : '';

        return $key === '' ? null : $key;
    }

    /** @return array<string,mixed> */
    private function templateArray(EmailTemplate $template): array
    {
        return [
            'id'             => $template->id,
            'uuid'           => $template->uuid,
            'slug'           => $template->slug,
            'name'           => $template->name,
            'status'         => $template->status,
            'draft_version'  => $template->draftVersion,
            'active_version' => $template->activeVersion,
            'updated_by'     => $template->updatedBy,
            'updated_at'     => $template->updatedAt->format(DATE_ATOM),
        ];
    }

    /** @return array<string,mixed> */
    private function versionArray(EmailTemplateVersion $version): array
    {
        return [
            'id'              => $version->id,
            'template_id'     => $version->templateId,
            'version'         => $version->versionNumber,
            'subject'         => $version->subject,
            'from_name'       => $version->fromName,
            'from_address'    => $version->fromAddress,
            'html_body'       => $version->htmlBody,
            'plain_text'      => $version->plainText,
            'plain_text_mode' => $version->plainTextMode,
            'layout_id'       => $version->layoutId,
            'layout_version'  => $version->layoutVersion,
            'variable_keys'   => $version->variableKeys,
            'created_by'      => $version->createdBy,
            'created_at'      => $version->createdAt->format(DATE_ATOM),
            'checksum'        => $version->checksum,
        ];
    }

    /** @return array<string,mixed> */
    private function layoutArray(EmailLayout $layout): array
    {
        return [
            'id' => $layout->id, 'slug' => $layout->slug, 'name' => $layout->name,
            'version' => $layout->version, 'status' => $layout->status,
            'regions' => $layout->regions, 'dependency' => $layout->dependency,
            'updated_at' => $layout->createdAt->format(DATE_ATOM),
        ];
    }

    /** @return array<string,mixed> */
    private function partialArray(EmailPartial $partial): array
    {
        return [
            'id' => $partial->id, 'slug' => $partial->slug, 'name' => $partial->name,
            'kind' => $partial->kind, 'html_body' => $partial->htmlBody, 'plain_text' => $partial->plainText,
            'status' => $partial->status, 'version' => $partial->version,
        ];
    }

    /** @return array<string,mixed> */
    private function routeArray(EmailRoute $route): array
    {
        return [
            'id' => $route->id, 'trigger' => $route->trigger, 'template_id' => $route->templateId,
            'recipient_rules' => $route->recipientRules, 'reply_to_rule' => $route->replyToRule,
            'flow_id' => $route->flowId, 'template_version_policy' => $route->templateVersionPolicy,
            'enabled' => $route->enabled, 'priority' => $route->priority,
            'updated_at' => $route->updatedAt->format(DATE_ATOM),
        ];
    }

    /** @return array<string,mixed> */
    private function captureArray(CapturedEmail $capture): array
    {
        return [
            'id' => $capture->id, 'capture_id' => $capture->captureId, 'attempt_id' => $capture->attemptId,
            'to' => $capture->to, 'subject' => $capture->subject,
            'html_body' => $capture->body, 'plain_text' => $capture->plainText, 'headers' => $capture->headers,
            'captured_at' => $capture->capturedAt->format(DATE_ATOM),
            'retention_until' => $capture->retentionUntil?->format(DATE_ATOM),
            'path' => $capture->path, 'state' => $capture->state,
        ];
    }

    /** @return array<string,mixed> */
    private function attemptArray(EmailAttempt $attempt): array
    {
        return [
            'id' => $attempt->id, 'uuid' => $attempt->attemptId,
            'attempt_id' => $attempt->attemptId, 'request_id' => $attempt->requestId,
            'parent_attempt_id' => $attempt->parentAttemptId, 'recipient' => $attempt->recipient,
            'subject' => $attempt->subject, 'template_slug' => $attempt->templateSlug, 'state' => $attempt->state,
            'template_id' => $attempt->templateId, 'template_version' => $attempt->templateVersion,
            'route_id' => $attempt->routeId,
            'provider' => $attempt->provider, 'provider_event' => $attempt->providerEvent,
            'provider_message_id' => $attempt->providerMessageId, 'error_code' => $attempt->errorCode,
            'retryable' => $attempt->retryable, 'occurred_at' => $attempt->occurredAt->format(DATE_ATOM),
            'created_at' => $attempt->occurredAt->format(DATE_ATOM),
            'updated_at' => $attempt->occurredAt->format(DATE_ATOM),
            'source' => $attempt->source,
            'recipient_hash' => $attempt->recipientHash,
            'environment' => $attempt->environment,
            'missing_variables' => $attempt->missingVariables,
        ];
    }

    /** @return array<string,mixed> */
    private function templateSummary(EmailTemplate $template): array
    {
        $summary = $this->templateArray($template);
        $versions = $this->templates->versions($template->id);
        $latest = $versions === [] ? null : $versions[array_key_last($versions)];
        $summary['subject'] = $latest?->subject ?? '';
        $summary['is_default'] = $this->defaults?->isDefault($template->slug) ?? false;

        return $summary;
    }

    /** @return list<array<string,mixed>> */
    private function overviewHealth(): array
    {
        $health = [];
        foreach ($this->templates->all() as $template) {
            if ($template->draftVersion < 1) {
                $health[] = ['template_id' => $template->id, 'status' => 'warning', 'errors' => ['draft' => __('No draft has been saved.', 'corex')]];
                continue;
            }
            $version = $this->templates->findVersion($template->id, $template->draftVersion);
            if ($version === null) {
                continue;
            }
            $layout = $this->layouts->findVersion($version->layoutId, $version->layoutVersion);
            $errors = $this->studio->health($version, new EmailHealthContext(
                providerConfigured: $this->delivery->providerConfigured(),
                requiresSubscriptionLinks: $layout?->slug === 'newsletter',
                layout: $layout,
            ));
            $health[] = ['template_id' => $template->id, 'status' => $errors === [] ? 'healthy' : 'warning', 'errors' => $errors];
        }

        return $health;
    }

    /** @param array<string,mixed> $data */
    private function success(array $data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response(ResponseEnvelope::success($data)->toArray(), $status);
    }

    /** @param array<string,mixed> $details */
    private function error(string $code, string $message, int $status, array $details = []): WP_REST_Response
    {
        return new WP_REST_Response(ResponseEnvelope::error($code, $message, $details)->toArray(), $status);
    }
}
