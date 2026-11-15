<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Config\Settings\Providers\CloudflareTokenProbe;
use Corex\Config\Settings\Providers\PageSpeedKeyProbe;
use Corex\Config\Settings\Providers\ProviderTestOutcome;
use Corex\Support\Config\ConfigInterface;

/**
 * Tests the Insights providers' keys and remembers the result (spec 119, US-SET-03).
 *
 * The keys are read from the config engine, as Insights reads them, so a key in the site's `.env`
 * is the one tested. The captcha test is the captcha add-on's own route; its result reaches the
 * same log through {@see record()}.
 */
final class ProviderTestService
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ProviderSetup $setup,
        private readonly ProviderTestLog $log,
        private readonly PageSpeedKeyProbe $pageSpeed,
        private readonly CloudflareTokenProbe $cloudflare,
    ) {
    }

    /**
     * Hears a test run by an add-on (`corex_provider_tested`) and answers its line
     * (`corex_provider_test_status`), so corex-config never depends on the add-on.
     */
    public function listen(): void
    {
        add_action('corex_provider_tested', [$this, 'onTested'], 10, 3);
        add_filter('corex_provider_test_status', [$this, 'statusFor'], 10, 2);
    }

    public function onTested(mixed $provider, mixed $worked, mixed $reason = ''): void
    {
        if (is_string($provider) && ProviderTestLog::knows($provider)) {
            $this->record($provider, (bool) $worked, is_string($reason) ? $reason : '');
        }
    }

    public function statusFor(mixed $line, mixed $provider): string
    {
        return is_string($provider) && ProviderTestLog::knows($provider) ? $this->statusLine($provider) : (string) $line;
    }

    /** Whether the provider has a key to test. */
    public function testable(string $provider): bool
    {
        return $this->setup->hasKeyFor($provider);
    }

    /** Runs the test, remembers it, and answers the line the page shows for it. */
    public function test(string $provider): ProviderTestRecord
    {
        $outcome = match ($provider) {
            ProviderTestLog::PAGESPEED  => $this->pageSpeed->probe($this->text('insights.psi.key')),
            ProviderTestLog::CLOUDFLARE => $this->cloudflare->probe(
                $this->text('insights.cloudflare.token'),
                $this->text('insights.cloudflare.account_id'),
            ),
            default => ProviderTestOutcome::failed(__('this provider has no test here', 'corex')),
        };

        return $this->record($provider, $outcome->worked, $outcome->reason);
    }

    /** Remembers a test run elsewhere (the captcha add-on's), and answers its line. */
    public function record(string $provider, bool $worked, string $reason): ProviderTestRecord
    {
        $at = time();
        $this->log->record($provider, $worked, $reason, $at);

        return new ProviderTestRecord($at, $worked, $reason);
    }

    /** The line for the provider's latest test of the values in force. */
    public function statusLine(string $provider): string
    {
        return $this->log->latest($provider)?->line() ?? ProviderTestRecord::untested();
    }

    private function text(string $key): string
    {
        $value = $this->config->get($key, '');

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
