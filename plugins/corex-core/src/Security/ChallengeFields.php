<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security;

defined('ABSPATH') || exit;

/**
 * The markup a protected CoreX form carries for the captcha provider's check: the hidden field its
 * token is sent in, and the place where a provider that shows a widget draws it. One definition for
 * every form, whichever plugin draws it: a form built in the admin, a form defined in code, the account
 * registration form and the newsletter signup (spec 104, spec 115).
 *
 * The token field is printed empty. The provider's script fills it when the visitor sends the form, and
 * reads the action to ask for from the field itself.
 */
final class ChallengeFields
{
    public const TOKEN_KEY = 'captcha_token';

    public static function token(string $action): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="" class="corex-form__captcha-token" data-corex-captcha-action="%s" />',
            esc_attr(self::TOKEN_KEY),
            esc_attr($action),
        );
    }

    /**
     * Where a provider's widget is drawn, for a provider that shows one ('' for one that does not).
     */
    public static function widgetPlace(string $provider, string $siteKey): string
    {
        return $provider === ''
            ? ''
            : sprintf(
                '<div class="corex-form__challenge" data-corex-challenge="%s" data-corex-sitekey="%s"></div>',
                esc_attr($provider),
                esc_attr($siteKey),
            );
    }
}
