<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant;

defined('ABSPATH') || exit;

use Corex\Admin\AdminPage;
use Corex\Assistant\Admin\AssistantScreen;
use Corex\Assistant\Admin\AssistantScreenSave;
use Corex\Assistant\Admin\AssistantScreenView;
use Corex\Assistant\Admin\ServiceCheck;
use Corex\Assistant\Admin\ServiceCheckController;
use Corex\Assistant\Booking\OpenSlots;
use Corex\Assistant\Booking\SlotClaims;
use Corex\Assistant\Booking\SlotMoment;
use Corex\Assistant\Booking\SlotsController;
use Corex\Assistant\Booking\SubmissionSlots;
use Corex\Assistant\Booking\WpSlotClaims;
use Corex\Assistant\Chain\ChatRouter;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\ModelClient;
use Corex\Assistant\Forms\AssistantCallbackForm;
use Corex\Assistant\Forms\AssistantSlotRule;
use Corex\Assistant\Forms\SlotClaimListener;
use Corex\Assistant\Front\AssistantButton;
use Corex\Assistant\Front\AssistantStrings;
use Corex\Assistant\Http\AssistantController;
use Corex\Assistant\Http\AssistantLimitsMiddleware;
use Corex\Assistant\Knowledge\KnowledgeProvider;
use Corex\Assistant\Knowledge\SiteKnowledge;
use Corex\Assistant\Limits\VisitorLimits;
use Corex\Assistant\Limits\VisitorLock;
use Corex\Blocks\BlockMap;
use Corex\Blocks\DynamicBlockRegistrar;
use Corex\Container\ContainerInterface;
use Corex\Forms\FormRegistry;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Foundation\ServiceProvider;
use Corex\Http\ClientAddress;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\Pipeline;
use Corex\Security\Admin\AdminGuard;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\SettingLocks;

/**
 * Wires the CoreX Assistant (spec 129): the chat route and its limits, the slots route and the claims of
 * requested times, the button in the footer, the hand-off form and its rule, and the CoreX → Assistant screen.
 *
 * The knowledge is asked of the `corex_assistant_knowledge_provider` filter when the chat first needs
 * it, so a site plugin that adds the filter on `init` or earlier is heard.
 */
final class AssistantServiceProvider extends ServiceProvider
{
    /** The question "Check services" asks, with the full instructions, at a real question's size. */
    private const CHECK_QUESTION = 'What do you offer, and how do I get started?';

    public function register(): void
    {
        $this->container->singleton(AssistantSettings::class, static fn (ContainerInterface $c): AssistantSettings => new AssistantSettings(
            $c->make(ConfigInterface::class),
            static fn (string $name, mixed $default): mixed => get_option($name, $default),
            (string) wp_timezone_string(),
        ));
        $this->container->singleton(Cooldowns::class, static fn (): Cooldowns => Cooldowns::fromWordPress());
        $this->container->singleton(ModelClient::class, static fn (): ModelClient => ModelClient::fromWordPress());
        $this->container->singleton(VisitorLock::class, static fn (): VisitorLock => VisitorLock::fromWordPress());
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
        $this->container->singleton(ChatRouter::class, static fn (ContainerInterface $c): ChatRouter => new ChatRouter(
            $c->make(ModelClient::class),
            $c->make(Cooldowns::class),
            $c->make(VisitorLimits::class),
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
        $this->registerBooking();
        $this->container->singleton(AssistantButton::class, fn (ContainerInterface $c): AssistantButton => new AssistantButton(
            $c->make(AssistantSettings::class),
            $c->make(AssistantStrings::class),
            fn (): string => $this->formsActive() ? (string) rest_url('corex/v1/forms/' . AssistantCallbackForm::SLUG) : '',
        ));
        $this->container->singleton(ServiceCheck::class, fn (ContainerInterface $c): ServiceCheck => new ServiceCheck(
            $c->make(AssistantSettings::class),
            $c->make(ModelClient::class),
            $c->make(Cooldowns::class),
            $c->make(VisitorLimits::class),
            static fn (): array => $c->make(AssistantService::class)->conversation(
                [['role' => 'user', 'content' => self::CHECK_QUESTION]],
                AssistantButton::language((string) get_locale()),
            ),
            static fn (): float => microtime(true),
        ));
        $this->container->singleton(ServiceCheckController::class, static fn (ContainerInterface $c): ServiceCheckController => new ServiceCheckController(
            $c->make(ServiceCheck::class),
            $c->make(Pipeline::class),
            $c->make(MiddlewareResolver::class),
        ));
        $this->registerScreen();
    }

    public function boot(): void
    {
        add_action('init', [$this, 'install']);
        add_action('rest_api_init', function (): void {
            $this->container->make(AssistantController::class)->register();
            $this->container->make(SlotsController::class)->register();
            $this->container->make(ServiceCheckController::class)->register();
        });
        $this->container->make(SubmissionSlots::class)->register();
        add_action('wp_footer', function (): void {
            $this->container->make(AssistantButton::class)->printInFooter();
        }, 5);
        if (is_admin()) {
            $this->container->make(AssistantScreen::class)->register();
        }
        $this->forgetKnowledgeOnChange();
    }

    /**
     * The knowledge is built again as soon as what it is made of changes: a page saved, published,
     * unpublished or binned, the site's name or tagline, an update that finished. A site whose own
     * provider reads other content fires `corex_assistant_forget_knowledge` when that content changes.
     */
    private function forgetKnowledgeOnChange(): void
    {
        $forget = static function (): void {
            SiteKnowledge::forget();
        };
        add_action('save_post_page', $forget);
        add_action('transition_post_status', static function (string $new, string $old, \WP_Post $post) use ($forget): void {
            if ($post->post_type === 'page' && $new !== $old && in_array('publish', [$new, $old], true)) {
                $forget();
            }
        }, 10, 3);
        add_action('deleted_post', static function (int $id, \WP_Post $post) use ($forget): void {
            if ($post->post_type === 'page') {
                $forget();
            }
        }, 10, 2);
        foreach (['update_option_blogname', 'update_option_blogdescription', 'upgrader_process_complete', 'corex_assistant_forget_knowledge'] as $hook) {
            add_action($hook, $forget);
        }
    }

    /**
     * The claims of requested times, the times still open, the slots route, and what follows a request in the
     * inbox (spec 129, FR-021).
     */
    private function registerBooking(): void
    {
        $now = static fn (): int => time();
        $this->container->singleton(SlotClaims::class, static function (): SlotClaims {
            global $wpdb;

            return new WpSlotClaims($wpdb);
        });
        $this->container->singleton(OpenSlots::class, static fn (ContainerInterface $c): OpenSlots => new OpenSlots(
            $c->make(AssistantSettings::class),
            $c->make(SlotClaims::class),
            $now,
        ));
        $this->container->singleton(SlotsController::class, static fn (ContainerInterface $c): SlotsController => new SlotsController($c->make(OpenSlots::class)));
        $this->container->singleton(SlotClaimListener::class, static fn (ContainerInterface $c): SlotClaimListener => new SlotClaimListener($c->make(SlotClaims::class)));
        $this->container->singleton(SubmissionSlots::class, static fn (ContainerInterface $c): SubmissionSlots => new SubmissionSlots(
            $c->make(SlotClaims::class),
            static fn (int $id): ?int => get_post_meta($id, 'corex_form_slug', true) === AssistantCallbackForm::SLUG
                ? SlotMoment::read(get_post_meta($id, 'corex_field_slot', true))
                : null,
            $now,
        ));
    }

    /**
     * A moment on a zone's clock, as the team reads it in the site's language ("Thu 12 Nov, 7:00 pm"): the
     * request may come from a visitor whose own language the page was drawn in.
     */
    private static function teamClock(int $at, string $zone): string
    {
        $switched = switch_to_locale((string) get_locale());
        /* translators: the day of a requested call time, as PHP's date() writes it: "Thu 12 Nov". */
        $line = (string) wp_date(__('D j M', 'corex') . ', ' . (string) get_option('time_format', 'g:i a'), $at, new \DateTimeZone($zone));
        if ($switched) {
            restore_previous_locale();
        }

        return $line;
    }

    /** The block, and the hand-off form and its rules when the forms engine is there to take them (Principle IX). */
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

        if (! $this->formsActive()) {
            return;
        }
        $rules = $this->container->make(RuleRegistry::class);
        $slots = $this->container->make(OpenSlots::class);
        if (! $rules->has(AssistantSlotRule::NAME)) {
            $rules->register(AssistantSlotRule::NAME, new AssistantSlotRule($slots));
        }
        $settings = $this->container->make(AssistantSettings::class);
        $this->container->make(FormRegistry::class)->register(new AssistantCallbackForm(
            $slots,
            static fn (int $at, string $zone): string => $settings->booking()->describe($at, $zone, self::teamClock(...)),
        ));
    }

    /**
     * The languages the owner writes the assistant's words in: the site's, English, and any a site adds
     * with the `corex_assistant_languages` filter (a bilingual site adds its second language).
     *
     * @return list<string>
     */
    public static function languages(): array
    {
        $languages = apply_filters('corex_assistant_languages', [AssistantButton::language((string) get_locale()), 'en']);
        $languages = is_array($languages) ? $languages : [];

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $code): string => is_string($code) ? AssistantButton::language($code) : '', $languages),
            static fn (string $code): bool => $code !== '',
        )));
    }

    private function registerScreen(): void
    {
        $locked = fn (string $key): bool => $this->container->has(SettingLocks::class)
            && $this->container->make(SettingLocks::class)->lockOf($key) !== null;
        $languages = static fn (): array => self::languages();

        $this->container->singleton(AssistantScreenView::class, static fn (ContainerInterface $c): AssistantScreenView => new AssistantScreenView(
            $c->make(AssistantSettings::class),
            $c->make(Cooldowns::class),
            $c->make(VisitorLimits::class),
            $locked,
            $languages,
            static fn (int $at): string => (string) wp_date(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'), $at),
            self::previewZones(),
        ));
        $this->container->singleton(AssistantScreenSave::class, static fn (ContainerInterface $c): AssistantScreenSave => new AssistantScreenSave(
            $c->make(Cooldowns::class),
            static function (string $option, mixed $value, bool $autoload): void {
                update_option($option, $value, $autoload);
                // `update_option()` changes nothing for an unchanged value, autoload included: a key an
                // earlier build stored unloaded would stay unread by the Config engine.
                if ($autoload) {
                    wp_set_option_autoload($option, true);
                }
            },
            static function (string $option): void {
                delete_option($option);
            },
            $locked,
            $languages,
            (string) wp_timezone_string(),
        ));
        $this->container->singleton(AssistantScreen::class, static fn (ContainerInterface $c): AssistantScreen => new AssistantScreen(
            $c->make(AdminPage::class),
            $c->make(AdminGuard::class),
            $c->make(AssistantScreenView::class),
            $c->make(AssistantScreenSave::class),
            dirname(__DIR__) . '/corex-assistant.php',
        ));
    }

    /**
     * The zones the booking hours are previewed in on the screen: the site's own and UTC, unless a site
     * names the ones its visitors are in (`corex_assistant_preview_zones`).
     *
     * @return list<string>
     */
    private static function previewZones(): array
    {
        $zones = apply_filters('corex_assistant_preview_zones', [(string) wp_timezone_string(), 'UTC']);

        return array_values(array_unique(array_filter(is_array($zones) ? $zones : [], 'is_string')));
    }

    private function formsActive(): bool
    {
        return class_exists(FormRegistry::class) && $this->container->has(FormRegistry::class);
    }
}
