<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Email;

defined('ABSPATH') || exit;

use Corex\Email\Design\EmailKind;
use Corex\Email\Studio\DefaultEmailTemplate;
use Corex\Email\Studio\DefaultEmailTemplates;

/**
 * The two Email Studio templates the account add-on ships (spec 120, story 6): the password reset
 * and the welcome a new member receives. Added to CoreX Mail's defaults when it is active, and
 * installed as every default is: active on a new site, drafts on an upgraded one.
 */
final class AccountEmailTemplates
{
    public const RESET = 'password-reset';

    public const WELCOME = 'account-welcome';

    public static function addTo(DefaultEmailTemplates $defaults): void
    {
        $defaults->add(self::RESET, static fn (): DefaultEmailTemplate => new DefaultEmailTemplate(
            self::RESET,
            __('Password reset', 'corex'),
            EmailKind::ACCOUNT,
            'transactional',
            /* translators: %s: the site's name, filled in when the email is sent. */
            sprintf(__('Reset your password for %s', 'corex'), '{{ site.name }}'),
            '<h1>' . esc_html__('Reset your password', 'corex') . '</h1>'
            /* translators: 1: when the reset was asked. 2: the account's email address. 3: how long the link works. All filled in when the email is sent. */
            . '<p>' . sprintf(esc_html__('On %1$s we were asked to reset the password of the account %2$s. Choose a new one with the button below. The link works for %3$s.', 'corex'), '{{ reset.asked }}', '{{ user.email }}', '{{ reset.expires }}') . '</p>'
            . '<p><a class="button" href="{{ action.url }}">' . esc_html__('Reset password', 'corex') . '</a></p>'
            . '<p>' . esc_html__('Button not working? Paste this link into your browser:', 'corex') . '<br />{{ action.url }}</p>'
            . '<p>' . esc_html__('Did not ask for this? Ignore this email and your password stays the same.', 'corex') . '</p>',
        ));
        $defaults->add(self::WELCOME, static fn (): DefaultEmailTemplate => new DefaultEmailTemplate(
            self::WELCOME,
            __('Welcome to your account', 'corex'),
            EmailKind::ACCOUNT,
            'transactional',
            /* translators: %s: the site's name, filled in when the email is sent. */
            sprintf(__('Welcome to %s', 'corex'), '{{ site.name }}'),
            /* translators: %s: the member's name, filled in when the email is sent. */
            '<h1>' . sprintf(esc_html__('Welcome, %s.', 'corex'), '{{ user.name }}') . '</h1>'
            /* translators: %s: the site's name, filled in when the email is sent. */
            . '<p>' . sprintf(esc_html__('Your account on %s is ready. Sign in with the password you chose.', 'corex'), '{{ site.name }}') . '</p>'
            . '<p><a class="button" href="{{ account.url }}">' . esc_html__('Go to your account', 'corex') . '</a></p>',
        ));
    }
}
