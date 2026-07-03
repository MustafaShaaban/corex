<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Email;

use Corex\Admin\AdminPage;
use Corex\Config\Overview\EnvironmentMode;
use Corex\Container\ContainerInterface;
use Corex\Email\Log\EmailLogRepository;
use Corex\Email\Template\EmailTemplate;
use Corex\Email\Template\Layout;
use Corex\Email\Template\MailContext;
use Corex\Email\Template\TemplateRegistry;
use Corex\Email\Template\TemplateRenderer;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Email Studio admin screen (spec 063 Phase 2 + spec 067). corex-email is an OPTIONAL add-on, so the
 * screen gates on it (Principle IX) — when inactive it shows an honest "not active" state, never a fake
 * studio. When active it is the designed multi-tab surface (design: "Corex Email Studio" + "Corex Email
 * Templates Admin"):
 *  - Studio tabs: Overview · Templates · Layouts · Partials · Variables.
 *  - Template detail (?template=slug): Edit · Preview · Plain text · Test send · Routing · Delivery logs.
 * Real where the engine supports it (real registered templates + subjects, real rendered Preview/Plain
 * text via {@see TemplateRenderer}, real Delivery logs via {@see EmailLogRepository}); honestly gated with
 * an exact reason where it does not (visual editor, test send, per-template routing, a partial system).
 * No fabricated templates, sends, or delivery metrics.
 */
final class EmailStudioScreen
{
    private const EMAIL_PLUGIN = 'corex-email/corex-email.php';

    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly EmailStudio $studio,
        private readonly EnvironmentMode $mode,
        private readonly ContainerInterface $container,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Email Studio', 'corex'),
            __('Email Studio', 'corex'),
            'manage_options',
            'corex-email-studio',
            [$this, 'render'],
            27,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        wp_enqueue_style(
            'corex-email-studio',
            plugins_url('assets/email-studio.css', COREX_CONFIG_FILE),
            ['corex-admin-shell'],
            '1.0.0',
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('email');

            return;
        }

        echo $this->page->open(
            'email',
            __('CoreX Email Studio', 'corex'),
            __('Transactional email templates, layouts, variables, and delivery state for this site.', 'corex'),
        );

        if (! $this->emailActive()) {
            echo $this->page->state(
                'warning',
                __('CoreX Email is not active', 'corex'),
                __('Activate the CoreX Email add-on to manage transactional templates and delivery.', 'corex'),
            );
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=corex-addons')) . '">'
                . esc_html__('Open Add-ons', 'corex') . '</a></p>';
            echo $this->page->close();

            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selection.
        $template = isset($_GET['template']) ? sanitize_key(wp_unslash($_GET['template'])) : '';
        if ($template !== '' && $this->registry()?->find($template) !== null) {
            echo $this->templateDetail($template);
            echo $this->page->close();

            return;
        }

        $tabs = [
            'overview'  => __('Overview', 'corex'),
            'templates' => __('Templates', 'corex'),
            'layouts'   => __('Layouts', 'corex'),
            'partials'  => __('Partials', 'corex'),
            'variables' => __('Variables', 'corex'),
        ];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        $active = array_key_exists($tab, $tabs) ? $tab : 'overview';

        echo $this->page->tabs('corex-email-studio', $tabs, $active, __('Email Studio sections', 'corex'));

        echo match ($active) {
            'templates' => $this->templatesTab(),
            'layouts'   => $this->layoutsTab(),
            'partials'  => $this->partialsTab(),
            'variables' => $this->variablesTab(),
            default     => $this->overviewTab(),
        };

        echo $this->page->close();
    }

    private function overviewTab(): string
    {
        $overview = $this->studio->overview(true, $this->templateNames(), $this->environment());
        $delivery = $overview['delivery'];
        [$sent, $failed] = $this->logCounts();

        return '<section class="corex-surface corex-email-studio__delivery is-' . esc_attr($delivery['tone']) . '">'
            . '<p class="corex-admin__eyebrow">' . esc_html__('DELIVERY MODE', 'corex') . '</p>'
            . '<h2>' . esc_html($delivery['label']) . '</h2>'
            . '<p class="corex-email-studio__detail">' . esc_html($delivery['detail']) . '</p>'
            . '<p><a href="' . esc_url(admin_url('admin.php?page=corex-settings-config&corex_tab=mail')) . '">'
            . esc_html__('Configure mail settings', 'corex') . '</a></p></section>'
            . '<div class="corex-email-studio__summary">'
            . $this->summaryStat(__('Templates', 'corex'), (string) $overview['templateCount'])
            . $this->summaryStat(__('Delivered (logged)', 'corex'), (string) $sent)
            . $this->summaryStat(__('Failed (logged)', 'corex'), (string) $failed)
            . '</div>';
    }

    private function summaryStat(string $label, string $value): string
    {
        return '<div class="corex-surface corex-email-studio__stat">'
            . '<div class="corex-email-studio__stat-label">' . esc_html($label) . '</div>'
            . '<div class="corex-email-studio__stat-value">' . esc_html($value) . '</div></div>';
    }

    private function templatesTab(): string
    {
        $registry = $this->registry();
        $names    = $registry ? $registry->names() : [];

        if ($names === []) {
            return $this->page->state('empty', __('No templates', 'corex'), __('No transactional email templates are registered yet.', 'corex'));
        }

        $rows = '';
        foreach ($names as $name) {
            $template = $registry?->find($name);
            $subject  = $template ? $this->safeSubject($template) : '';
            $rows .= '<a class="corex-email-studio__row" href="'
                . esc_url(add_query_arg(['page' => 'corex-email-studio', 'template' => $name], admin_url('admin.php')))
                . '"><code>' . esc_html($name) . '</code><span class="corex-email-studio__row-subject">'
                . esc_html($subject) . '</span><span class="corex-email-studio__row-status">'
                . esc_html__('Registered', 'corex') . '</span><span class="corex-email-studio__row-open">'
                . esc_html__('Open', 'corex') . '</span></a>';
        }

        return '<section class="corex-surface corex-email-studio__templates">'
            . '<header class="corex-email-studio__templates-head"><h2>' . esc_html__('Templates', 'corex') . '</h2>'
            . '<span class="corex-email-studio__count">' . sprintf(
                /* translators: %d: number of registered email templates */
                esc_html(_n('%d registered', '%d registered', count($names), 'corex')),
                count($names),
            ) . '</span></header>' . $rows . '</section>';
    }

    private function layoutsTab(): string
    {
        $preview = $this->layoutPreview();
        $previewHtml = $preview === null
            ? $this->page->state(
                'error',
                __('Layout preview unavailable', 'corex'),
                __('The active brand layout could not be rendered.', 'corex'),
            )
            : '<iframe class="corex-email-studio__preview" title="'
                . esc_attr__('Brand layout preview', 'corex') . '" sandbox srcdoc="'
                . esc_attr($preview) . '"></iframe>';

        return '<section class="corex-surface corex-email-studio__templates">'
            . '<header class="corex-email-studio__templates-head"><h2>' . esc_html__('Brand layout', 'corex') . '</h2>'
            . '<span class="corex-email-studio__count">' . esc_html__('1 active', 'corex') . '</span></header>'
            . '<p class="corex-email-studio__detail">'
            . esc_html__('One brand layout wraps every transactional email — a consistent header, body frame, and footer built from your brand tokens. Every template you send is rendered inside it.', 'corex')
            . '</p>' . $previewHtml . '<p class="corex-email-studio__note-text">'
            . esc_html__('Multiple selectable layouts and a visual layout builder are planned capabilities; today the single brand layout is applied automatically.', 'corex')
            . '</p></section>';
    }

    private function partialsTab(): string
    {
        return '<section class="corex-surface corex-email-studio__note">'
            . '<p class="corex-email-studio__note-title">' . esc_html__('Partials', 'corex') . '</p>'
            . '<p class="corex-email-studio__note-text">'
            . esc_html__('Reusable partials (shared buttons, callouts, signatures) are not implemented yet — each template composes its own body, and the brand layout supplies the shared header and footer. A partial library is a planned capability; nothing is faked here.', 'corex')
            . '</p></section>';
    }

    private function variablesTab(): string
    {
        $variables = $this->studio->variables($this->templateSources());

        if ($variables === []) {
            return $this->page->state(
                'empty',
                __('No merge placeholders detected', 'corex'),
                __('Registered templates do not currently expose any {{ path }} placeholders in their subject or body output.', 'corex'),
            );
        }

        $rows = '';
        foreach ($variables as $path => $templates) {
            $rows .= '<tr><td><code>{{ ' . esc_html($path) . ' }}</code></td><td>'
                . esc_html(implode(', ', $templates)) . '</td></tr>';
        }

        return '<section class="corex-surface corex-email-studio__templates">'
            . '<header class="corex-email-studio__templates-head"><h2>' . esc_html__('Variables', 'corex') . '</h2></header>'
            . '<p class="corex-email-studio__detail">'
            . esc_html__('Merge placeholders detected in the real registered template output. Context values consumed directly in template code are not introspectable and are not guessed here.', 'corex')
            . '</p><div class="corex-email-studio__table-scroll"><table class="corex-email-studio__vars"><thead><tr><th>'
            . esc_html__('Variable', 'corex') . '</th><th>' . esc_html__('Registered templates', 'corex')
            . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
    }

    private function templateDetail(string $name): string
    {
        $tabs = [
            'edit'    => __('Edit', 'corex'),
            'preview' => __('Preview', 'corex'),
            'plain'   => __('Plain text', 'corex'),
            'test'    => __('Test send', 'corex'),
            'routing' => __('Routing', 'corex'),
            'logs'    => __('Delivery logs', 'corex'),
        ];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
        $tab = isset($_GET['detail']) ? sanitize_key(wp_unslash($_GET['detail'])) : '';
        $active = array_key_exists($tab, $tabs) ? $tab : 'preview';

        $strip = '';
        foreach ($tabs as $key => $label) {
            $strip .= sprintf(
                '<a class="corex-admin__tab%1$s" href="%2$s"%3$s>%4$s</a>',
                $key === $active ? ' is-active' : '',
                esc_url(add_query_arg(['page' => 'corex-email-studio', 'template' => $name, 'detail' => $key], admin_url('admin.php'))),
                $key === $active ? ' aria-current="page"' : '',
                esc_html($label),
            );
        }

        $back = '<p><a href="' . esc_url(add_query_arg(['page' => 'corex-email-studio', 'tab' => 'templates'], admin_url('admin.php')))
            . '">' . esc_html__('All templates', 'corex') . '</a></p>';

        $body = match ($active) {
            'plain'   => $this->plainDetail($name),
            'edit'    => $this->editDetail(),
            'test'    => $this->testDetail(),
            'routing' => $this->routingDetail(),
            'logs'    => $this->logsDetail(),
            default   => $this->previewDetail($name),
        };

        return $back . '<h2 class="corex-email-studio__detail-title"><code>' . esc_html($name) . '</code></h2>'
            . '<nav class="corex-admin__tabs" aria-label="' . esc_attr__('Template detail', 'corex') . '">' . $strip . '</nav>'
            . $body;
    }

    private function previewDetail(string $name): string
    {
        $rendered = $this->renderTemplate($name);
        if ($rendered === null) {
            return $this->page->state('error', __('Preview unavailable', 'corex'), __('This template could not be rendered for preview.', 'corex'));
        }

        return '<p class="corex-email-studio__note-text">'
            . esc_html__('Real render of this template inside the brand layout, using empty sample values (a structural preview, not a send).', 'corex')
            . '</p><iframe class="corex-email-studio__preview" title="' . esc_attr__('Email preview', 'corex') . '" sandbox srcdoc="'
            . esc_attr($rendered['body']) . '"></iframe>';
    }

    private function plainDetail(string $name): string
    {
        $rendered = $this->renderTemplate($name);
        if ($rendered === null) {
            return $this->page->state('error', __('Preview unavailable', 'corex'), __('This template could not be rendered.', 'corex'));
        }

        $plain = trim(wp_strip_all_tags($rendered['body']));

        return '<p class="corex-email-studio__note-text">' . esc_html__('The plain-text fallback (tags stripped from the rendered body).', 'corex') . '</p>'
            . '<pre class="corex-email-studio__plain">' . esc_html($plain) . '</pre>';
    }

    private function editDetail(): string
    {
        return '<div class="corex-surface corex-email-studio__note">'
            . '<p class="corex-email-studio__note-title">' . esc_html__('Editing is code-defined', 'corex') . '</p>'
            . '<p class="corex-email-studio__note-text">'
            . esc_html__('This template is defined in code (an EmailTemplate class), so its subject and body are version-controlled and testable. A safe in-admin visual editor is a planned capability; until then, edit the template class to change it — this screen never silently overwrites a code template.', 'corex')
            . '</p></div>';
    }

    private function testDetail(): string
    {
        return '<div class="corex-surface corex-email-studio__note">'
            . '<p class="corex-email-studio__note-title">' . esc_html__('Test send', 'corex') . '</p>'
            . '<p class="corex-email-studio__note-text">'
            . esc_html__('Test send is disabled because the current Mailer contract has no per-send result for this screen to report delivered versus failed truthfully. A future action must target your own address, be capability + nonce gated, and expose that result before it can dispatch mail here.', 'corex')
            . '</p><button type="button" class="button" disabled aria-disabled="true">' . esc_html__('Send test (disabled)', 'corex') . '</button></div>';
    }

    private function routingDetail(): string
    {
        return '<div class="corex-surface corex-email-studio__note">'
            . '<p class="corex-email-studio__note-title">' . esc_html__('Routing', 'corex') . '</p>'
            . '<p class="corex-email-studio__note-text">'
            . esc_html__('Recipients, cc/bcc, and reply-to are set in code at each send site via the message builder (to a fixed address, a role, or a dynamic context path). A per-template routing editor is a planned capability; this template is routed where the sending event dispatches it.', 'corex')
            . '</p></div>';
    }

    private function logsDetail(): string
    {
        $repo = $this->logs();
        if ($repo === null) {
            return $this->page->state('empty', __('No delivery logs', 'corex'), __('Delivery logging is not available.', 'corex'));
        }

        $rows = '';
        foreach (['sent', 'failed'] as $status) {
            try {
                $entries = $repo->byStatus($status);
            } catch (\Throwable) {
                continue;
            }
            foreach ($entries as $entry) {
                $tone        = $status === 'sent' ? 'success' : 'danger';
                $statusLabel = $status === 'sent' ? __('Sent', 'corex') : __('Failed', 'corex');
                $subject     = (string) $entry->get('mail_subject', __('(no subject)', 'corex'));
                $rows .= '<li class="corex-email-studio__log is-' . esc_attr($tone) . '">'
                    . '<span>' . esc_html($subject) . '</span>'
                    . '<span class="corex-email-studio__log-meta">' . esc_html((string) $entry->get('recipients', ''))
                    . ' · ' . esc_html($statusLabel) . '</span></li>';
            }
        }

        if ($rows === '') {
            return $this->page->state('empty', __('No delivery logs yet', 'corex'), __('When CoreX sends a transactional email, each attempt (delivered or failed) is recorded here.', 'corex'));
        }

        return '<section class="corex-surface corex-email-studio__templates">'
            . '<header class="corex-email-studio__templates-head"><h2>' . esc_html__('Delivery logs', 'corex') . '</h2></header>'
            . '<ul class="corex-email-studio__logs">' . $rows . '</ul>'
            . '<p class="corex-email-studio__note-text">' . esc_html__('Real send attempts recorded on this site. Logs are not yet filtered per template.', 'corex') . '</p></section>';
    }

    // --- real-engine helpers (all optional, gated behind the add-on) ---

    private function registry(): ?TemplateRegistry
    {
        try {
            return $this->container->make(TemplateRegistry::class);
        } catch (\Throwable) {
            return null;
        }
    }

    private function logs(): ?EmailLogRepository
    {
        try {
            return $this->container->make(EmailLogRepository::class);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{subject:string,body:string}|null
     */
    private function renderTemplate(string $name): ?array
    {
        try {
            $template = $this->registry()?->find($name);
            if ($template === null) {
                return null;
            }
            /** @var TemplateRenderer $renderer */
            $renderer = $this->container->make(TemplateRenderer::class);
            $rendered = $renderer->render($template, new MailContext([]));

            return ['subject' => $rendered->subject, 'body' => $rendered->body];
        } catch (\Throwable) {
            return null;
        }
    }

    private function layoutPreview(): ?string
    {
        try {
            /** @var Layout $layout */
            $layout = $this->container->make(Layout::class);

            return $layout->wrap(
                __('Example transactional email', 'corex'),
                '<p>' . esc_html__('Your transactional email content appears here.', 'corex') . '</p>',
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeSubject(EmailTemplate $template): string
    {
        try {
            return $template->subject(new MailContext([]));
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return array{0:int,1:int} [sent, failed]
     */
    private function logCounts(): array
    {
        $repo = $this->logs();
        if ($repo === null) {
            return [0, 0];
        }

        try {
            return [$repo->byStatus('sent')->count(), $repo->byStatus('failed')->count()];
        } catch (\Throwable) {
            return [0, 0];
        }
    }

    private function emailActive(): bool
    {
        return in_array(
            self::EMAIL_PLUGIN,
            array_map('strval', (array) get_option('active_plugins', [])),
            true,
        );
    }

    /**
     * @return list<string>
     */
    private function templateNames(): array
    {
        return $this->registry()?->names() ?? [];
    }

    /**
     * @return array<string,list<string>> Template name => subject/body sources.
     */
    private function templateSources(): array
    {
        $registry = $this->registry();
        if ($registry === null) {
            return [];
        }

        $sources = [];
        foreach ($registry->names() as $name) {
            try {
                $template = $registry->find($name);
                if ($template !== null) {
                    $context = new MailContext([]);
                    $sources[$name] = [$template->subject($context), $template->body($context)];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $sources;
    }

    /**
     * @return array{mode:string,label:string}
     */
    private function environment(): array
    {
        $env = $this->mode->resolve(
            function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : 'production',
        );

        return ['mode' => $env['mode'], 'label' => $env['label']];
    }
}
