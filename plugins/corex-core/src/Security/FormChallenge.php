<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security;

defined('ABSPATH') || exit;

/**
 * The anti-bot check a public CoreX form carries, for a form the forms plugin does not draw: account
 * registration, the newsletter signup, a site's own call request or job application form (spec 115). A
 * form prints {@see fields()} before its button and puts {@see formAttribute()} on its form element; its
 * route asks {@see refusal()} before it stores or sends anything.
 *
 * Core answers with {@see NoFormChallenge}: no check, every request passes, as a site without a captcha
 * provider always has. corex-captcha replaces it with the configured provider's check.
 */
interface FormChallenge
{
    /** The markup to print before the form's button, already escaped; '' when no check is needed. */
    public function fields(string $action): string;

    /** The attribute for the form element, so the provider's script binds to it; '' when none. */
    public function formAttribute(): string;

    /** Null when the token passes; otherwise the translated message to show the visitor. */
    public function refusal(string $token, string $action): ?string;
}
