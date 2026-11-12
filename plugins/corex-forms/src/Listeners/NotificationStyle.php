<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Listeners;

defined('ABSPATH') || exit;

use Closure;
use Corex\Support\Options\LoadedOptions;

/**
 * How this site's code-defined forms notify (spec 120, FR-023 and FR-018), decided once.
 *
 * A site that had received a form submission when this release first ran sends its code-defined
 * form notifications as it did before: the contact form through its template, every other form
 * as a finished HTML message, and the same HTML on the `wp_mail()` floor. A site with none yet
 * (a new one) sends them designed: every form through a template, in the one frame, and plain
 * text on the floor. The choice is stored and never made again; a site whose choice is not
 * stored yet sends as before.
 */
final readonly class NotificationStyle
{
    public const OPTION = 'corex_forms_notification_style';

    /** As before spec 120. */
    public const AS_BEFORE = 'as_before';

    /** Through a template, in the one frame; plain text on the floor. */
    public const DESIGNED = 'designed';

    public function designed(): bool
    {
        return get_option(self::OPTION, '') === self::DESIGNED;
    }

    /**
     * Stores the style once, the first time this release runs.
     *
     * @param Closure():bool $hadSubmissions Whether the site has stored a form submission: asked
     *                                       only when the style is not stored yet.
     */
    public function settle(Closure $hadSubmissions): void
    {
        if (get_option(self::OPTION, '') !== '') {
            return;
        }

        LoadedOptions::store(self::OPTION, $hadSubmissions() ? self::AS_BEFORE : self::DESIGNED);
    }
}
