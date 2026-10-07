<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

/**
 * Classifies a captcha "Test verification" (spec 044, US2) into a stable kind + a human
 * message. Pure and **secret-free by construction** — it is given only the presence of the
 * keys (booleans) and the provider's response shape, never the secret, so a result can
 * never carry one (FR-008 / SC-006).
 */
final class CaptchaDiagnostic
{
    public const OK             = 'ok';
    public const MISSING_KEYS   = 'missing_keys';
    public const INVALID_KEYS   = 'invalid_keys';
    public const NETWORK_ERROR  = 'network_error';
    public const NOT_APPLICABLE = 'not_applicable';

    /** The keys are good and nothing uses them: no widget for this driver is placed on a form. */
    public const NO_WIDGET      = 'no_widget';

    private const KEY_DRIVERS    = ['recaptcha', 'turnstile', 'hcaptcha'];
    private const SECRET_ERRORS  = ['invalid-input-secret', 'missing-input-secret'];

    /** Drivers with a verifier and no browser side yet. Accepted keys for one protect nothing. */
    private const WITHOUT_WIDGET = ['turnstile', 'hcaptcha'];

    private function __construct(
        public readonly string $kind,
        public readonly string $message,
    ) {
    }

    public static function ofKind(string $kind): self
    {
        return new self($kind, self::messageFor($kind));
    }

    /**
     * Direct classification from booleans (used in unit tests and the simple paths).
     */
    public static function classify(
        string $driver,
        bool $hasSiteKey,
        bool $hasSecret,
        ?bool $verified,
        bool $networkError = false,
    ): self {
        if (! in_array($driver, self::KEY_DRIVERS, true)) {
            return self::ofKind(self::NOT_APPLICABLE);
        }
        if (! $hasSiteKey || ! $hasSecret) {
            return self::ofKind(self::MISSING_KEYS);
        }
        if ($networkError) {
            return self::ofKind(self::NETWORK_ERROR);
        }

        return $verified === true ? self::accepted($driver) : self::ofKind(self::INVALID_KEYS);
    }

    /**
     * Classification from a provider siteverify response. A probe token that fails *only*
     * because the token is bad (not the secret) proves the keys are valid → `ok`.
     *
     * @param array<string,mixed>|null $body the decoded siteverify body, or null on no/bad response
     */
    public static function fromVerifyResponse(
        string $driver,
        bool $hasSiteKey,
        bool $hasSecret,
        ?array $body,
        bool $networkError = false,
    ): self {
        if (! in_array($driver, self::KEY_DRIVERS, true)) {
            return self::ofKind(self::NOT_APPLICABLE);
        }
        if (! $hasSiteKey || ! $hasSecret) {
            return self::ofKind(self::MISSING_KEYS);
        }
        if ($networkError || $body === null) {
            return self::ofKind(self::NETWORK_ERROR);
        }
        if (($body['success'] ?? false) === true) {
            return self::accepted($driver);
        }

        $codes = (array) ($body['error-codes'] ?? $body['error_codes'] ?? []);

        if (array_intersect($codes, self::SECRET_ERRORS) !== []) {
            return self::ofKind(self::INVALID_KEYS);
        }

        // A token-only error (our probe token) means the secret was accepted — keys are valid.
        return self::accepted($driver);
    }

    /**
     * The provider accepted the keys. Whether that means a form is protected depends on whether
     * anything places the provider's widget. "Keys accepted" for one that nothing places told an
     * operator their forms were challenged when they were not.
     */
    private static function accepted(string $driver): self
    {
        return self::ofKind(in_array($driver, self::WITHOUT_WIDGET, true) ? self::NO_WIDGET : self::OK);
    }

    private static function messageFor(string $kind): string
    {
        return match ($kind) {
            self::OK             => __('The captcha keys were accepted.', 'corex'),
            self::MISSING_KEYS   => __('Add the site key and secret key for this driver.', 'corex'),
            self::INVALID_KEYS   => __('The captcha provider rejected the keys — double-check them.', 'corex'),
            self::NETWORK_ERROR  => __('Could not reach the captcha provider — check connectivity and try again.', 'corex'),
            self::NO_WIDGET      => __('The keys were accepted, but CoreX does not place this provider’s widget on its forms yet, so it challenges nobody. Forms are still guarded by the trap field. Choose reCAPTCHA for a challenge.', 'corex'),
            default              => __('This driver needs no keys to test.', 'corex'),
        };
    }
}
