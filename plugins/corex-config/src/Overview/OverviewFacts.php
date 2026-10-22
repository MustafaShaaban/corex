<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

use Corex\Config\Insights\InsightStore;
use Corex\Config\Security\LoginProtection\LoginAddressState;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginRecovery;
use Corex\Multisite\PluginActivationInspector;
use Corex\Support\Config\ConfigInterface;

defined('ABSPATH') || exit;

/**
 * The facts behind the Overview's state rows (spec 114, US-SAFE-10), each read from where it
 * lives: the brand from its saved name and logo, PageSpeed from its saved key and its latest run,
 * the captcha from its add-on and its keys, and the login address from the Security Center's own
 * rule. Each fact that comes from another part of CoreX is read on its own, and one that cannot
 * be read is `null`, which the model says as "Could not check": a fact nobody could read is never
 * drawn as a success.
 *
 * The keys are read through Config, so a key set in the environment counts as saved.
 */
final class OverviewFacts
{
    public const CAPTCHA_PLUGIN = 'corex-captcha/corex-captcha.php';

    private const INSIGHTS_OPTION = 'corex_insights';

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly PluginActivationInspector $plugins,
        private readonly InsightStore $insights,
        private readonly LoginProtectionSettingsStore $loginSettings,
        private readonly LoginRecovery $recovery,
    ) {
    }

    /**
     * @param array<string,mixed> $settingValues settings values keyed by Config dot-key
     *
     * @return array{
     *   brand: array{name:bool,logo:bool},
     *   mailFrom: bool,
     *   insights: array{key:bool,latest:array<string,mixed>|null}|null,
     *   captcha: array{active:bool,driver:string,keys:bool}|null,
     *   login: string|null
     * }
     */
    public function gather(array $settingValues): array
    {
        return [
            'brand'    => [
                'name' => $this->filled($settingValues['brand.company_name'] ?? ''),
                'logo' => $this->filled($settingValues['brand.logo_url'] ?? ''),
            ],
            'mailFrom' => $this->filled($settingValues['mail.from.address'] ?? ''),
            'insights' => $this->guarded(fn (): array => [
                'key'    => $this->filled($this->config->get('insights.psi.key', '')),
                'latest' => $this->insights->latest((array) get_option(self::INSIGHTS_OPTION, []), 'performance'),
            ]),
            'captcha'  => $this->guarded(fn (): array => [
                'active' => $this->plugins->isActive(self::CAPTCHA_PLUGIN),
                'driver' => trim((string) $this->config->get('captcha.driver', 'none')),
                'keys'   => $this->filled($this->config->get('captcha.site_key', ''))
                    && $this->filled($this->config->get('captcha.secret', '')),
            ]),
            'login'    => $this->guarded(fn (): string => LoginAddressState::of(
                $this->loginSettings->current(),
                $this->recovery->active(),
            )),
        ];
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

    private function filled(mixed $value): bool
    {
        return is_scalar($value) && trim((string) $value) !== '';
    }
}
