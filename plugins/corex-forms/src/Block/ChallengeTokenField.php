<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Security\ChallengeFields;

/**
 * The hidden field a protected form sends its challenge token in, for a form built in the
 * admin and a form defined in code alike.
 *
 * It is printed empty. The provider's script fills it when the visitor submits, and reads the
 * action to ask for from the field itself, so two forms that share a name each get their own
 * (spec 104, FR-017). The markup itself is core's {@see ChallengeFields}, which the add-ons' forms
 * print too (spec 115).
 */
final class ChallengeTokenField
{
    /**
     * Where a provider's widget is rendered, for a provider that shows one. The add-on's script
     * finds it, asks the provider to draw there, and writes the token into the form's field.
     */
    public static function widgetPlace(string $provider, string $siteKey): string
    {
        return ChallengeFields::widgetPlace($provider, $siteKey);
    }

    public static function render(string $action): string
    {
        return ChallengeFields::token($action);
    }
}
