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
    public static function render(string $action): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="" class="corex-form__captcha-token" data-corex-captcha-action="%s" />',
            esc_attr(SubmissionChallenge::TOKEN_KEY),
            esc_attr($action),
        );
    }
}
