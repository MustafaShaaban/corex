<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Corex\Guides;

defined('ABSPATH') || exit;

use Corex\Guides\Guide;
use Corex\Guides\GuideStep;
use Corex\Guides\GuideTopic;

/**
 * Every settings section, and every field in it (spec 094).
 *
 * The requirement was "every single detail and input should be described", and Settings is where
 * that bites: nine sections, 42 fields, and a screen that otherwise explains itself only through
 * short help text written for somebody who already knows what the field is for.
 *
 * Three things are said here that the screen cannot say for itself, and they are the reason this is
 * a guide rather than more help text:
 *
 * - **A password field that looks empty may not be.** Secrets are write-only: submitting a blank one
 *   keeps the stored value. Somebody who assumes blank means unset either pastes the key again
 *   (harmless) or concludes it never saved (not).
 * - **Six captcha fields appear and disappear** with the chosen driver. A reader hunting for "Site
 *   key" on Honeypot concludes the screen is broken.
 * - **The Advanced section stores nothing.** It reads the server back to you.
 *
 * `SettingsGuideCoverageTest` asserts every key in `SettingsRegistry::keys()` is named here, so a
 * field added without documentation fails the suite rather than quietly going unexplained.
 */
final class SettingsGuide
{
    public static function guide(): Guide
    {
        return Guide::for('corex-settings', __('Settings, field by field', 'corex'))
            ->withSummary(__('What every setting does, what a good value looks like, and which ones you can safely ignore.', 'corex'))
            ->inSection('general')
            ->onScreen('admin.php?page=corex-settings-config')
            ->requiring('corex_manage_settings')
            ->ordered(20)
            ->withTopic(self::howItWorks())
            ->withTopic(self::brand())
            ->withTopic(self::mail())
            ->withTopic(self::formsAndCaptcha())
            ->withTopic(self::media())
            ->withTopic(self::insights())
            ->withTopic(self::dashboardAndGuides())
            ->withTopic(self::advanced());
    }

    private static function howItWorks(): GuideTopic
    {
        return GuideTopic::for(
            'how-settings-work',
            __('Before you change anything', 'corex'),
            __('Three behaviours that are not obvious from looking at the screen.', 'corex'),
            [
                new GuideStep(
                    __('Notice the tabs across the top: Brand, Mail, Forms, Captcha, Media, Insights, Dashboard, Guides, Advanced.', 'corex'),
                    __('Each is a separate section. Saving applies to the whole screen, not only the tab you are looking at.', 'corex'),
                ),
                new GuideStep(
                    __('Leave a password field blank unless you are changing it.', 'corex'),
                    __('Secrets are never shown back to you, so an empty-looking box usually means it is already set. Saving it blank keeps what is stored rather than clearing it.', 'corex'),
                    warning: __('This means you cannot tell by looking whether a key is set. If you are unsure, paste it again — that is harmless.', 'corex'),
                ),
                new GuideStep(
                    __('Expect some fields to appear only when they are relevant.', 'corex'),
                    __('The Captcha section in particular changes shape with the driver you pick. A field you cannot find is usually a field that does not apply.', 'corex'),
                ),
            ],
        );
    }

    private static function brand(): GuideTopic
    {
        return GuideTopic::for(
            'brand',
            __('Brand — your company details', 'corex'),
            __('The section with the widest effect: these values appear across the site, in emails, and on the login screen.', 'corex'),
            [
                new GuideStep(
                    __('Company name — your organisation as people should see it written.', 'corex'),
                    __('Used in the site chrome, the footer, the Setup Wizard, and the subject line of emails the site sends.', 'corex'),
                ),
                new GuideStep(
                    __('Tagline — one short line describing what you do.', 'corex'),
                    __('Optional. It appears where a kit design has a place for it.', 'corex'),
                ),
                new GuideStep(
                    __('Contact phone and Contact email — how the public reaches you.', 'corex'),
                    __('These are the published details page patterns use. They are not where form notifications go — that is the Forms section below.', 'corex'),
                ),
                new GuideStep(
                    __('Address — your postal address, on one line.', 'corex'),
                    __('Used in footers and contact sections.', 'corex'),
                ),
                new GuideStep(
                    __('Primary action label and Primary action link — your main call to action.', 'corex'),
                    __('For example "Get a quote" pointing at your contact page. Kit patterns use this pair for their main button, so setting it once updates every one of them.', 'corex'),
                ),
                new GuideStep(
                    __('Social links — your profile addresses, separated by commas.', 'corex'),
                    __('Full web addresses, not handles.', 'corex'),
                ),
                new GuideStep(
                    __('Admin logo — choose an image from your media library.', 'corex'),
                    __('It appears on the CoreX admin and on the WordPress login screen. A wide, simple image works best, because it is displayed small.', 'corex'),
                ),
                new GuideStep(
                    __('Admin footer text — replaces the text at the bottom of admin screens.', 'corex'),
                    __('Leave it blank for "Powered by Corex".', 'corex'),
                ),
                new GuideStep(
                    __('CoreX admin appearance — System, Light, or Dark.', 'corex'),
                    __('System follows whatever your computer is set to. This is your own preference and does not change what visitors see.', 'corex'),
                ),
                new GuideStep(
                    __('Enable SSO login section — leave this off.', 'corex'),
                    __('It reserves space on the login screen for single sign-on. No provider is configured yet, so turning it on adds an empty section and nothing else.', 'corex'),
                ),
            ],
        );
    }

    private static function mail(): GuideTopic
    {
        return GuideTopic::for(
            'mail',
            __('Mail — who your site sends as', 'corex'),
            __('Nothing here sends email. It decides how outgoing email identifies itself, and whether it may leave at all.', 'corex'),
            [
                new GuideStep(
                    __('From name and From address — who the email appears to be from.', 'corex'),
                    __('Leave them blank to use the site name and the administrator address. Use an address at your own domain: a Gmail or Outlook address here is the most common reason mail lands in spam.', 'corex'),
                ),
                new GuideStep(
                    __('Default reply-to address — where replies go.', 'corex'),
                    __('Useful when you send from something like noreply@ but want answers to reach a real inbox.', 'corex'),
                ),
                new GuideStep(
                    __('Delivery provider — Disabled, or WordPress wp_mail.', 'corex'),
                    __('This chooses how mail physically leaves. Disabled means nothing is sent.', 'corex'),
                ),
                new GuideStep(
                    __('Enable live delivery — the switch that lets real email out.', 'corex'),
                    __('Off by default, deliberately. Until both this and a provider are set, the site composes email without sending it, which is what you want while building.', 'corex'),
                    warning: __('Turn this on only after you have sent yourself a test and it arrived. Switching it on with a misconfigured sender means real people get nothing and nobody tells you.', 'corex'),
                ),
            ],
        );
    }

    private static function formsAndCaptcha(): GuideTopic
    {
        return GuideTopic::for(
            'forms-captcha',
            __('Forms and Captcha — where messages go, and keeping spam out', 'corex'),
            __('Two small sections that belong together in practice.', 'corex'),
            [
                new GuideStep(
                    __('Form notification recipient — who is told when somebody submits a form.', 'corex'),
                    __('Leave it blank to use the site administrator. This is separate from the public Contact email in Brand: one is published, this one is internal.', 'corex'),
                ),
                new GuideStep(
                    __('Captcha driver — None, Honeypot, reCAPTCHA, Cloudflare Turnstile, or hCaptcha.', 'corex'),
                    __('Honeypot needs no account and no keys, and stops ordinary spam. Start there, and move to a provider only if spam gets through.', 'corex'),
                ),
                new GuideStep(
                    __('Site key and Secret key — shown only for reCAPTCHA, Turnstile and hCaptcha.', 'corex'),
                    __('Both come from the provider dashboard, and the screen links to the right page for the driver you chose. The site key is public; the secret key never reaches the browser.', 'corex'),
                    warning: __('If you cannot see these fields, you are on None or Honeypot. That is not a fault — those drivers have no keys.', 'corex'),
                ),
                new GuideStep(
                    __('reCAPTCHA v3 score threshold — shown only for reCAPTCHA.', 'corex'),
                    __('A number between 0 and 1, defaulting to 0.3. Higher rejects more visitors, including real ones. Watch your traffic before raising it.', 'corex'),
                ),
                new GuideStep(
                    __('reCAPTCHA v3 action (global default) — shown only for reCAPTCHA, and usually left blank.', 'corex'),
                    __('Each form already derives its own action from its name. Set this only if you need every form to report one shared action.', 'corex'),
                ),
                new GuideStep(
                    __('Allowed hostnames — shown only for reCAPTCHA.', 'corex'),
                    __('Comma-separated exact hostnames a verification may come from, such as example.com, staging.example.com. Blank means this site host only.', 'corex'),
                ),
            ],
        );
    }

    private static function media(): GuideTopic
    {
        return GuideTopic::for(
            'media',
            __('Media — smaller images, automatically', 'corex'),
            __('This section only appears when the Media add-on is active.', 'corex'),
            [
                new GuideStep(
                    __('Server support — read this line first.', 'corex'),
                    __('It reports what your server can actually do. If it says WebP is unsupported, nothing below has any effect and the fix is with your host.', 'corex'),
                ),
                new GuideStep(
                    __('Convert uploads to WebP — the main switch.', 'corex'),
                    __('On upload, a smaller WebP copy is written alongside the original. Your originals are always kept.', 'corex'),
                ),
                new GuideStep(
                    __('WebP quality — a number from 1 to 100, defaulting to 82.', 'corex'),
                    __('82 balances file size against visible quality. Below about 70 the difference starts to show on photographs.', 'corex'),
                ),
                new GuideStep(
                    __('Minimum size saving (%) — defaulting to 5.', 'corex'),
                    __('The WebP copy is served only when it is at least this much smaller, which stops the site serving a "smaller" file that is not.', 'corex'),
                ),
                new GuideStep(
                    __('Convert JPEG uploads and Convert PNG uploads — which formats to convert.', 'corex'),
                    __('Both on by default. Turn off PNG if you upload images with transparency and see problems.', 'corex'),
                ),
            ],
        );
    }

    private static function insights(): GuideTopic
    {
        return GuideTopic::for(
            'insights',
            __('Insights — keys for the performance and security checks', 'corex'),
            __('Optional. Without them the Insights screen simply reports fewer things.', 'corex'),
            [
                new GuideStep(
                    __('PageSpeed Insights API key — for the performance check.', 'corex'),
                    __('Free from Google. Without it, performance checks are skipped rather than reported as failing.', 'corex'),
                ),
                new GuideStep(
                    __('Cloudflare API token and Cloudflare account ID — for the security scan.', 'corex'),
                    __('Only relevant if your site is behind Cloudflare. The account ID is on your Cloudflare dashboard home.', 'corex'),
                ),
                new GuideStep(
                    __('Remember that both keys are write-only.', 'corex'),
                    __('They will look blank the next time you open this screen even though they are stored.', 'corex'),
                ),
            ],
        );
    }

    private static function dashboardAndGuides(): GuideTopic
    {
        return GuideTopic::for(
            'dashboard-guides',
            __('Dashboard and Guides — small preferences', 'corex'),
            __('Two short sections controlling what you see.', 'corex'),
            [
                new GuideStep(
                    __('Attention widget — lists your unread CoreX notifications on the WordPress dashboard.', 'corex'),
                    __('It hides itself when you have none, so turning it on costs nothing on a quiet site.', 'corex'),
                ),
                new GuideStep(
                    __('Development widget — shows the operating mode and its warnings.', 'corex'),
                    __('It only ever appears while the site is in Development, so it cannot clutter a live site.', 'corex'),
                ),
                new GuideStep(
                    __('Support email — where "Still stuck?" messages from the Guides screen are sent.', 'corex'),
                    __('Leave it blank to use the address this add-on ships with. Set it to a real inbox somebody reads.', 'corex'),
                ),
                new GuideStep(
                    __('Offer the support form — turn the contact panel off entirely.', 'corex'),
                    __('Use this if you would rather people reached you another way. Switching it off removes the panel; it does not leave a form that discards what somebody typed.', 'corex'),
                ),
            ],
        );
    }

    private static function advanced(): GuideTopic
    {
        return GuideTopic::for(
            'advanced',
            __('Advanced — nothing to change here', 'corex'),
            __('This section stores no settings. It reads your server back to you, so you can answer a support question without a hosting login.', 'corex'),
            [
                new GuideStep(
                    __('PHP version and WordPress version — what this site is running on.', 'corex'),
                    __('CoreX needs PHP 8.3 or newer and WordPress 7.0 or newer.', 'corex'),
                ),
                new GuideStep(
                    __('Environment type — Development, Staging or Production.', 'corex'),
                    __('Set by your hosting, not here. Change it under Operations & Security, where the consequences are explained before you confirm.', 'corex'),
                ),
                new GuideStep(
                    __('PHP memory limit and Multisite — two facts a host will ask you for.', 'corex'),
                    __('If something large fails — a big import, an export of thousands of rows — the memory limit is the first number to quote.', 'corex'),
                ),
            ],
        );
    }
}
