<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\SubmissionChallenge;

/**
 * The hidden field a protected form sends its challenge token in, for a form built in the
 * admin and a form defined in code alike.
 *
 * It is printed empty. The provider's script fills it when the visitor submits, and reads the
 * action to ask for from the field itself, so two forms that share a name each get their own
 * (spec 104, FR-017).
 */
final class ChallengeTokenField
{
    /**
     * Where a provider's widget is rendered, for a provider that shows one. The add-on's script
     * finds it, asks the provider to draw there, and writes the token into the form's field.
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

    public static function render(string $action): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="" class="corex-form__captcha-token" data-corex-captcha-action="%s" />',
            esc_attr(SubmissionChallenge::TOKEN_KEY),
            esc_attr($action),
        );
    }
}
