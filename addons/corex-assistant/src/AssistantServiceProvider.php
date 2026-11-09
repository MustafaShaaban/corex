<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant;

defined('ABSPATH') || exit;

use Corex\Assistant\Admin\AssistantPanel;
use Corex\Assistant\Admin\ServiceCheck;
use Corex\Assistant\Admin\ServiceCheckController;
use Corex\Assistant\Booking\BookingHours;
use Corex\Assistant\Booking\SlotsController;
use Corex\Assistant\Chain\ChatRouter;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\ModelClient;
use Corex\Assistant\Forms\AssistantCallbackForm;
use Corex\Assistant\Front\AssistantButton;
use Corex\Assistant\Front\AssistantStrings;
use Corex\Assistant\Http\AssistantController;
use Corex\Assistant\Http\AssistantLimitsMiddleware;
use Corex\Assistant\Knowledge\KnowledgeProvider;
use Corex\Assistant\Knowledge\SiteKnowledge;
use Corex\Assistant\Limits\PaidAnswers;
use Corex\Assistant\Limits\VisitorLimits;
use Corex\Assistant\Limits\VisitorLock;
use Corex\Blocks\BlockMap;
use Corex\Blocks\DynamicBlockRegistrar;
use Corex\Container\ContainerInterface;
use Corex\Forms\FormRegistry;
use Corex\Foundation\ServiceProvider;
use Corex\Http\ClientAddress;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\Pipeline;
use Corex\Support\Config\ConfigInterface;

/**
 * Wires the CoreX Assistant (spec 129): the chat route and its limits, the paid-answer count, the
 * button in the footer, the hand-off form and the times it offers, and the panel under Settings →
 * Assistant.
 *
 * The knowledge is asked of the `corex_assistant_knowledge_provider` filter when the chat first needs
 * it, so a site plugin that adds the filter on `init` or earlier is heard.
 */
final class AssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(AssistantSettings::class, static fn (ContainerInterface $c): AssistantSettings => new AssistantSettings($c->make(ConfigInterface::class)));
        $this->container->singleton(Cooldowns::class, static fn (): Cooldowns => Cooldowns::fromWordPress());
        $this->container->singleton(ModelClient::class, static fn (): ModelClient => ModelClient::fromWordPress());
        $this->container->singleton(VisitorLock::class, static fn (): VisitorLock => VisitorLock::fromWordPress());
        $this->container->singleton(PaidAnswers::class, static function (ContainerInterface $c): PaidAnswers {
            $settings = $c->make(AssistantSettings::class);

            return new PaidAnswers(
                static fn (string $key): mixed => get_transient($key),
                static function (string $key, int $value, int $seconds): void {
                    set_transient($key, $value, $seconds);
                },
                static fn (): int => time(),
                static fn (): int => $settings->paidCap(),
            );
        });
        $this->container->singleton(
            ChatRouter::class,
            static fn (ContainerInterface $c): ChatRouter => new ChatRouter(
                $c->make(ModelClient::class),
                $c->make(Cooldowns::class),
                static fn (): int => time(),
                $c->make(PaidAnswers::class),
            ),
        );
        $this->container->singleton(VisitorLimits::class, static function (ContainerInterface $c): VisitorLimits {
            $settings = $c->make(AssistantSettings::class);

            return new VisitorLimits(
                static fn (string $key): mixed => get_transient($key),
                static function (string $key, int $value, int $seconds): void {
                    set_transient($key, $value, $seconds);
                },
                static fn (): int => time(),
                static fn (): array => $settings->limits(),
            );
        });
        $this->container->singleton(SlotsController::class, static fn (ContainerInterface $c): SlotsController => new SlotsController(
            $c->make(AssistantSettings::class),
            static fn (): string => (string) wp_timezone_string(),
            static fn (): int => time(),
        ));
        $this->container->singleton(
            AssistantLimitsMiddleware::class,
            static fn (ContainerInterface $c): AssistantLimitsMiddleware => new AssistantLimitsMiddleware($c->make(VisitorLock::class), $c->make(VisitorLimits::class)),
        );
        $this->container->singleton(KnowledgeProvider::class, static function (): KnowledgeProvider {
            $provider = apply_filters('corex_assistant_knowledge_provider', null);

            return $provider instanceof KnowledgeProvider ? $provider : SiteKnowledge::fromWordPress();
        });
        $this->container->singleton(AssistantStrings::class, static fn (ContainerInterface $c): AssistantStrings => new AssistantStrings(
            $c->make(AssistantSettings::class),
            static fn (): string => html_entity_decode((string) get_bloginfo('name'), ENT_QUOTES, 'UTF-8'),
        ));
        $this->container->singleton(AssistantService::class, static fn (ContainerInterface $c): AssistantService => new AssistantService(
            $c->make(AssistantSettings::class),
            $c->make(ChatRouter::class),
            $c->make(KnowledgeProvider::class),
            $c->make(AssistantStrings::class),
            (string) wp_parse_url((string) home_url('/'), PHP_URL_HOST),
        ));
        $this->container->singleton(AssistantController::class, static fn (ContainerInterface $c): AssistantController => new AssistantController(
            $c->make(AssistantService::class),
            $c->make(Pipeline::class),
            $c->make(MiddlewareResolver::class),
            $c->make(AssistantLimitsMiddleware::class),
            $c->make(ClientAddress::class),
            static fn (): string => wp_salt('nonce'),
        ));
        $this->container->singleton(AssistantButton::class, fn (ContainerInterface $c): AssistantButton => new AssistantButton(
            $c->make(AssistantSettings::class),
            $c->make(AssistantStrings::class),
            fn (): string => $this->formsActive() ? (string) rest_url('corex/v1/forms/' . AssistantCallbackForm::SLUG) : '',
        ));
        $this->container->singleton(ServiceCheck::class, static fn (ContainerInterface $c): ServiceCheck => new ServiceCheck(
            $c->make(AssistantSettings::class),
            $c->make(ModelClient::class),
            $c->make(Cooldowns::class),
            $c->make(AssistantService::class),
            $c->make(AssistantStrings::class),
            static fn (): string => AssistantButton::language((string) get_locale()),
            static fn (): float => microtime(true),
        ));
        $this->container->singleton(ServiceCheckController::class, static fn (ContainerInterface $c): ServiceCheckController => new ServiceCheckController(
            $c->make(ServiceCheck::class),
            $c->make(Pipeline::class),
            $c->make(MiddlewareResolver::class),
        ));
        $this->container->singleton(AssistantPanel::class, static fn (ContainerInterface $c): AssistantPanel => new AssistantPanel(
            $c->make(AssistantSettings::class),
            $c->make(Cooldowns::class),
            $c->make(VisitorLimits::class),
            $c->make(PaidAnswers::class),
            $c->make(SlotsController::class),
            static fn (int $at): string => (string) wp_date(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'), $at),
        ));
    }

    public function boot(): void
    {
        add_action('init', [$this, 'install']);
        add_action('rest_api_init', function (): void {
            $this->container->make(AssistantController::class)->register();
            $this->container->make(ServiceCheckController::class)->register();
            $this->container->make(SlotsController::class)->register();
        });
        add_action('wp_footer', function (): void {
            $this->container->make(AssistantButton::class)->printInFooter();
        }, 5);
        add_filter('corex_settings_section_extra', function (mixed $html, mixed $section): string {
            return $this->container->make(AssistantPanel::class)->extra($html, $section);
        }, 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
        add_action('save_post_page', [SiteKnowledge::class, 'forget']);
    }

    /** The block, and the hand-off form when the forms engine is there to take it (Principle IX). */
    public function install(): void
    {
        // The built block when a build has run, else its source (the markup renders; its scripts wait for the build).
        $built = dirname(__DIR__) . '/build/blocks';
        $blocks = is_dir($built) ? $built : dirname(__DIR__) . '/blocks';
        if ($this->container->has(DynamicBlockRegistrar::class)) {
            $registrar = $this->container->make(DynamicBlockRegistrar::class);
            foreach ($this->container->make(BlockMap::class)->discover($blocks) as $block) {
                $registrar->register($block);
            }
        }

        if ($this->formsActive()) {
            $settings = $this->container->make(AssistantSettings::class);
            $this->container->make(FormRegistry::class)->register(new AssistantCallbackForm(
                static fn (): BookingHours => $settings->booking((string) wp_timezone_string()),
                // The team reads the time in the site's language: wp_date() names the days and months.
                static fn (int $utc, \DateTimeZone $zone): string => (string) wp_date(BookingHours::TEAM_FORMAT, $utc, $zone),
            ));
        }
    }

    /** "Check services" on Settings → Assistant, and only there (Principle VI). */
    public function enqueueAdmin(string $hook): void
    {
        // Which Settings page is open; read only to choose what to load, so no nonce applies.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $asked = $_GET['section'] ?? $_GET['corex_tab'] ?? '';
        $page = is_string($asked) ? sanitize_key(wp_unslash($asked)) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if (! str_ends_with($hook, '_page_corex-settings-config') || $page !== AssistantPanel::PAGE) {
            return;
        }

        $base = dirname(__DIR__) . '/corex-assistant.php';
        wp_enqueue_script('corex-assistant-admin', plugins_url('assets/assistant-admin.js', $base), ['wp-i18n'], COREX_ASSISTANT_VERSION, true);
        wp_set_script_translations('corex-assistant-admin', 'corex');
        wp_localize_script('corex-assistant-admin', 'corexAssistantAdmin', [
            'endpoint' => esc_url_raw(rest_url('corex/v1/assistant/check')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    private function formsActive(): bool
    {
        return class_exists(FormRegistry::class) && $this->container->has(FormRegistry::class);
    }
}
