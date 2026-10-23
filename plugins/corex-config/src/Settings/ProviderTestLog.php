<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Support\Config\ConfigInterface;

/**
 * The latest test of each provider's keys (spec 119, US-SET-03, FR-016, FR-017).
 *
 * A failed test left no trace, so a wrong key was found by a visitor. Each record keeps when the
 * test ran, whether the keys worked and the reason in words, and a one-way fingerprint of the
 * values the test used: an HMAC with the site's salt, so the record cannot be turned back into a
 * key. When any of those values changes, here or in the site's `.env`, the fingerprint no longer
 * matches and the provider reads "Not tested yet" again. No key is ever stored.
 */
final class ProviderTestLog
{
    public const CAPTCHA    = 'captcha';
    public const PAGESPEED  = 'pagespeed';
    public const CLOUDFLARE = 'cloudflare';

    public const OPTION = 'corex_provider_tests';

    /** The values each provider's test uses. */
    private const KEYS = [
        self::CAPTCHA    => ['captcha.driver', 'captcha.site_key', 'captcha.secret'],
        self::PAGESPEED  => ['insights.psi.key'],
        self::CLOUDFLARE => ['insights.cloudflare.token', 'insights.cloudflare.account_id'],
    ];

    public function __construct(private readonly ConfigInterface $config)
    {
    }

    public static function knows(string $provider): bool
    {
        return isset(self::KEYS[$provider]);
    }

    public function record(string $provider, bool $worked, string $reason, int $at): void
    {
        if (! self::knows($provider)) {
            return;
        }

        $records            = $this->all();
        $records[$provider] = [
            'at'          => $at,
            'worked'      => $worked,
            'reason'      => $reason,
            'fingerprint' => $this->fingerprint($provider),
        ];

        update_option(self::OPTION, $records, false);
    }

    /** The latest test of the values in force, or null when they were never tested. */
    public function latest(string $provider): ?ProviderTestRecord
    {
        $record = $this->all()[$provider] ?? null;
        if (! is_array($record) || ! hash_equals($this->fingerprint($provider), (string) ($record['fingerprint'] ?? ''))) {
            return null;
        }

        return new ProviderTestRecord((int) ($record['at'] ?? 0), (bool) ($record['worked'] ?? false), (string) ($record['reason'] ?? ''));
    }

    /** @return array<string,mixed> */
    private function all(): array
    {
        $stored = get_option(self::OPTION, []);

        return is_array($stored) ? $stored : [];
    }

    private function fingerprint(string $provider): string
    {
        $values = [];
        foreach (self::KEYS[$provider] ?? [] as $key) {
            $value    = $this->config->get($key, '');
            $values[] = is_scalar($value) ? trim((string) $value) : '';
        }

        return hash_hmac('sha256', $provider . "\0" . implode("\0", $values), wp_salt('auth'));
    }
}
