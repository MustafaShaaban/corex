<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

use Corex\Support\Config\ConfigInterface;

/**
 * Selects the configured captcha driver and supplies its secret from the Config engine
 * (`captcha.driver`, `captcha.secret`). Switching provider is configuration, not code.
 * Unknown/absent driver → NullCaptcha (the honeypot still guards).
 *
 * `recaptcha` resolves to the scored v3 driver ({@see RecaptchaV3Captcha}); `turnstile` and
 * `hcaptcha` keep the simpler `success`-only {@see RemoteCaptcha}. The v3 driver's stateful
 * collaborator — the replay guard — is injected rather than constructed here, so this resolver
 * stays a thin factory over configuration.
 */
final class CaptchaResolver
{
    private const ENDPOINTS = [
        'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'hcaptcha'  => 'https://hcaptcha.com/siteverify',
    ];

    private TokenReplayGuard $replay;

    public function __construct(
        private readonly ConfigInterface $config,
        ?TokenReplayGuard $replay = null,
    ) {
        // Defaulted so existing one-argument callers keep working; the service provider passes
        // the container-bound guard. The guard is a stateless-by-construction utility (its state
        // lives in transients), so a default instance is behaviourally identical.
        $this->replay = $replay ?? new TokenReplayGuard();
    }

    /**
     * The provider siteverify endpoint for a key-driver, or null for none/honeypot/unknown.
     * Exposed so the "Test verification" action can probe the configured provider.
     */
    public static function endpoint(string $driver): ?string
    {
        return self::ENDPOINTS[$driver] ?? null;
    }

    public function resolve(): Captcha
    {
        $driver = (string) $this->config->get('captcha.driver', 'none');
        $secret = trim((string) $this->config->get('captcha.secret', ''));

        // A key driver with no secret is *half* configured, and half configured is not configured.
        // Building the real driver anyway meant every verification failed, so selecting a provider
        // in Settings and not yet pasting the keys silently rejected every submission on the site
        // behind a message that named none of that. The honeypot still guards; CaptchaDiagnostic
        // already classifies this exact state as `missing_keys` and is where the operator is told.
        if ($secret === '' && isset(self::ENDPOINTS[$driver])) {
            return new NullCaptcha();
        }

        return match ($driver) {
            'honeypot' => new HoneypotCaptcha(),
            'recaptcha' => new RecaptchaV3Captcha(self::ENDPOINTS['recaptcha'], $secret, $this->replay),
            'turnstile', 'hcaptcha' => new RemoteCaptcha(self::ENDPOINTS[$driver], $secret),
            default => new NullCaptcha(),
        };
    }
}
