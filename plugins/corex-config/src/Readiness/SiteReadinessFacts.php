<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Readiness;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Settings\SettingsStore;
use Corex\Container\ContainerInterface;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Multisite\PluginActivationInspector;
use Corex\Provisioning\KitProvisioner;
use DateInterval;
use DateTimeImmutable;

/**
 * Reads the facts behind the "Site and content" checks (spec 118, US-OVR-03) from where each one
 * lives: the brand from its saved settings, the kit from the kit provisioner, the front page from
 * the Reading settings and the theme's templates, the legal pages from WordPress's privacy page
 * and CoreX's terms page setting, the test email from Email Studio's attempts, and login
 * protection from the Security Center. Each fact is read on its own; one that cannot be read is
 * null, which {@see SiteChecks} says as "Could not check".
 *
 * The kit and Corex Mail are optional add-ons, so both are asked for lazily (Principle IX).
 */
final class SiteReadinessFacts
{
    public const EMAIL_PLUGIN = 'corex-email/corex-email.php';

    public const TERMS_PAGE_KEY = 'brand.terms_page';

    public function __construct(
        private readonly SettingsStore $settings,
        private readonly LoginProtectionSettingsStore $login,
        private readonly PluginActivationInspector $plugins,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @return array{
     *   brand: array{name:bool,logo:bool}|null,
     *   kit: array{present:bool,applied:bool}|null,
     *   frontPage: array{static:bool,template:bool}|null,
     *   privacy: array{page:int,published:bool}|null,
     *   terms: array{page:int,published:bool}|null,
     *   testEmail: array{mail:bool,sent:bool}|null,
     *   login: bool|null
     * }
     */
    public function gather(DateTimeImmutable $now): array
    {
        return [
            'brand'     => $this->guarded(fn (): array => [
                'name' => trim($this->settings->get('brand.company_name')) !== '',
                'logo' => trim($this->settings->get('brand.logo_url')) !== '',
            ]),
            'kit'       => $this->guarded($this->kit(...)),
            'frontPage' => $this->guarded(fn (): array => [
                'static'   => get_option('show_on_front') === 'page' && (int) get_option('page_on_front') > 0,
                'template' => $this->themeHasFrontPage(),
            ]),
            'privacy'   => $this->guarded(fn (): array => $this->page((int) get_option('wp_page_for_privacy_policy'))),
            'terms'     => $this->guarded(fn (): array => $this->page((int) $this->settings->get(self::TERMS_PAGE_KEY))),
            'testEmail' => $this->guarded(fn (): array => $this->testEmail($now)),
            'login'     => $this->guarded(fn (): bool => $this->login->current()->enabled),
        ];
    }

    /** @return array{present:bool,applied:bool} */
    private function kit(): array
    {
        /** @var KitProvisioner $provisioner */
        $provisioner = $this->container->make(KitProvisioner::class);
        $kits        = $provisioner->applicableKits();

        return [
            'present' => $kits !== [],
            'applied' => array_filter($kits, static fn ($kit): bool => $kit->applied) !== [],
        ];
    }

    /**
     * A theme draws its own front page when it ships a `front-page` template: a block template for a
     * block theme, `front-page.php` for a classic one.
     */
    private function themeHasFrontPage(): bool
    {
        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            return get_block_templates(['slug__in' => ['front-page']], 'wp_template') !== [];
        }

        return locate_template(['front-page.php']) !== '';
    }

    /**
     * A chosen page and whether it is published. A page id that names no page is no choice.
     *
     * @return array{page:int,published:bool}
     */
    private function page(int $id): array
    {
        $status = $id > 0 ? get_post_status($id) : false;

        return $status === false
            ? ['page' => 0, 'published' => false]
            : ['page' => $id, 'published' => $status === 'publish'];
    }

    /** @return array{mail:bool,sent:bool} */
    private function testEmail(DateTimeImmutable $now): array
    {
        if (! $this->plugins->isActive(self::EMAIL_PLUGIN) || ! class_exists(EmailAttemptRepository::class)) {
            return ['mail' => false, 'sent' => false];
        }

        /** @var EmailAttemptRepository $attempts */
        $attempts = $this->container->make(EmailAttemptRepository::class);
        $since    = $now->sub(new DateInterval('P' . SiteChecks::TEST_EMAIL_DAYS . 'D'));

        return ['mail' => true, 'sent' => $attempts->latestAcceptedTest($since) !== null];
    }

    /**
     * @template T
     *
     * @param callable():T $read
     *
     * @return T|null
     */
    private function guarded(callable $read): mixed
    {
        try {
            return $read();
        } catch (\Throwable) {
            return null;
        }
    }
}
