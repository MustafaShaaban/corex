<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * Declares the configurable Corex settings — sections and fields. Pure, apart from reading the
 * site's pages for the terms page choice. Each field key is a Config dot-key, so saving it
 * persists to the option the Config engine reads.
 */
final class SettingsRegistry implements FieldSections
{
    /**
     * @return array<string,array{title:string,fields:array<string,array{label:string,type:string,options?:array<string,string>}>}>
     */
    public function sections(): array
    {
        return [
            'brand' => [
                'title'  => __('Brand', 'corex'),
                'fields' => [
                    'brand.company_name' => [
                        'label' => __('Company name', 'corex'),
                        'type'  => 'text',
                        'help'  => __('Used across the site chrome, footer, and the Setup Wizard brand step.', 'corex'),
                    ],
                    'brand.tagline' => [
                        'label' => __('Tagline', 'corex'),
                        'type'  => 'text',
                    ],
                    'brand.phone' => [
                        'label' => __('Contact phone', 'corex'),
                        'type'  => 'tel',
                    ],
                    'brand.email' => [
                        'label' => __('Contact email', 'corex'),
                        'type'  => 'email',
                    ],
                    'brand.address' => [
                        'label' => __('Address', 'corex'),
                        'type'  => 'text',
                    ],
                    'brand.primary_action_label' => [
                        'label' => __('Primary action label', 'corex'),
                        'type'  => 'text',
                        'help'  => __('The call-to-action button label used by kit patterns (e.g. "Get a quote").', 'corex'),
                    ],
                    'brand.primary_action_link' => [
                        'label' => __('Primary action link', 'corex'),
                        'type'  => 'url',
                    ],
                    'brand.social_links' => [
                        'label' => __('Social links', 'corex'),
                        'type'  => 'text',
                        'help'  => __('Comma-separated profile URLs.', 'corex'),
                    ],
                    'brand.logo_url'    => [
                        'label' => __('Admin logo', 'corex'),
                        'type'  => 'media',
                        'help'  => __('Used on the CoreX admin shell and the WordPress login screen.', 'corex'),
                    ],
                    // Where the login screen's logo links (spec 119, US-SET-13). Not the sign-in
                    // address: the Security Center decides that one.
                    'brand.login_url' => [
                        'label' => __('Login logo link', 'corex'),
                        'type'  => 'url',
                        'help'  => __('Where the logo on the login screen takes people. This is the logo\'s link, not the sign-in address. Leave blank for this site\'s home page.', 'corex'),
                    ],
                    'brand.footer_text' => [
                        'label' => __('Admin footer text', 'corex'),
                        'type'  => 'text',
                        'help'  => __('Replaces the wp-admin footer text. Leave blank for "Powered by Corex".', 'corex'),
                    ],
                    // The launch checklist's "Terms page published" reads it (spec 118, US-OVR-03).
                    'brand.terms_page' => [
                        'label'   => __('Terms page', 'corex'),
                        'type'    => 'select',
                        'options' => $this->pageChoices(),
                        'help'    => __('The page with your site\'s terms and conditions. The launch checklist checks that it is published.', 'corex'),
                    ],
                    'brand.admin_appearance' => [
                        'label'   => __('CoreX admin appearance', 'corex'),
                        'type'    => 'select',
                        'options' => ['system' => __('System', 'corex'), 'light' => __('Light', 'corex'), 'dark' => __('Dark', 'corex')],
                        'help'    => __('System follows your operating-system colour scheme.', 'corex'),
                    ],
                ],
            ],
            'mail' => [
                'title'  => __('Mail', 'corex'),
                'fields' => [
                    'mail.from.name'    => ['label' => __('From name', 'corex'), 'type' => 'text'],
                    'mail.from.address' => ['label' => __('From address', 'corex'), 'type' => 'email'],
                    'mail.reply_to'     => [
                        'label' => __('Default reply-to address', 'corex'),
                        'type'  => 'email',
                    ],
                    // Who a reply from the Submissions inbox is from (spec 106, story 5).
                    'mail.reply.from_name' => [
                        'label' => __('Reply sender name', 'corex'),
                        'type'  => 'text',
                        'help'  => __('The name a reply from the Submissions inbox is sent under. Leave blank for the From name above.', 'corex'),
                    ],
                    'mail.reply.from_address' => [
                        'label' => __('Reply sender address', 'corex'),
                        'type'  => 'email',
                        'help'  => __('The address a reply is sent from. It has to be a mailbox your mail service may send as, or the reply may be refused or land in spam. Leave blank for the From address above.', 'corex'),
                    ],
                    'mail.reply.reply_to' => [
                        'label' => __('Replies are answered to', 'corex'),
                        'type'  => 'email',
                        'help'  => __('Where the person\'s answer to a reply goes. Leave blank for the default reply-to address above.', 'corex'),
                    ],
                    'mail.provider'     => [
                        'label'   => __('Delivery provider', 'corex'),
                        'type'    => 'select',
                        'options' => [
                            ''        => __('Disabled', 'corex'),
                            'wp-mail' => __('WordPress wp_mail', 'corex'),
                        ],
                        'help' => __('Verify the site transport before enabling live delivery.', 'corex'),
                    ],
                    'mail.live_delivery' => [
                        'label' => __('Enable live delivery', 'corex'),
                        'type'  => 'checkbox',
                        'help'  => __('Production mail remains blocked unless a matching provider is also selected.', 'corex'),
                    ],
                    // What a designed email is drawn from (spec 106). Left blank, CoreX's own.
                    'mail.brand.accent' => [
                        'label' => __('Email accent colour', 'corex'),
                        'type'  => 'hex-colour',
                        'help'  => __('The edge along the top of an email. Six-digit hex, such as #0B1F3B. Leave blank for CoreX\'s.', 'corex'),
                    ],
                    'mail.brand.logo' => [
                        'label' => __('Email logo', 'corex'),
                        'type'  => 'media',
                        'help'  => __('Shown at the top of an email, fitted inside 180 by 48 pixels. Choose a PNG or JPEG: a mail client does not draw an SVG. Leave blank for the site\'s logo.', 'corex'),
                    ],
                    'mail.brand.footer' => [
                        'label' => __('Email footer line', 'corex'),
                        'type'  => 'text',
                        'help'  => __('The line under an email. Leave blank for "This message was sent by" and the site\'s name.', 'corex'),
                    ],
                ],
            ],
            'forms' => [
                'title'  => __('Forms', 'corex'),
                'fields' => [
                    'forms.email.recipient' => ['label' => __('Form notification recipient', 'corex'), 'type' => 'email'],
                ],
            ],
            'captcha' => [
                'title'  => __('Captcha', 'corex'),
                'fields' => [
                    'captcha.driver' => [
                        'label'   => __('Captcha driver', 'corex'),
                        'type'    => 'select',
                        'options' => [
                            'none'      => __('None', 'corex'),
                            'honeypot'  => __('Honeypot', 'corex'),
                            'recaptcha' => 'reCAPTCHA',
                            'turnstile' => 'Cloudflare Turnstile',
                            'hcaptcha'  => 'hCaptcha',
                        ],
                        'help' => __('Choose a provider, Honeypot (no keys), or None to disable. reCAPTCHA scores a visitor without showing anything; Turnstile and hCaptcha show their widget above a protected form’s button.', 'corex'),
                    ],
                    'captcha.site_key' => [
                        'label'         => __('Site key', 'corex'),
                        'type'          => 'text',
                        'show_for'      => ['key' => 'captcha.driver', 'values' => ['recaptcha', 'turnstile', 'hcaptcha']],
                        'help_variants' => [
                            'recaptcha' => [
                                'help'      => __('The public reCAPTCHA site key.', 'corex'),
                                'help_url'  => 'https://www.google.com/recaptcha/admin/create',
                                'help_link' => __('Create reCAPTCHA keys', 'corex'),
                            ],
                            'turnstile' => [
                                'help'      => __('The public Cloudflare Turnstile site key.', 'corex'),
                                'help_url'  => 'https://developers.cloudflare.com/turnstile/get-started/',
                                'help_link' => __('Get Turnstile keys', 'corex'),
                            ],
                            'hcaptcha' => [
                                'help'      => __('The public hCaptcha site key.', 'corex'),
                                'help_url'  => 'https://docs.hcaptcha.com/#add-the-hcaptcha-widget-to-your-webpage',
                                'help_link' => __('hCaptcha site key docs', 'corex'),
                            ],
                        ],
                    ],
                    'captcha.secret' => [
                        'label'         => __('Secret key', 'corex'),
                        'type'          => 'password',
                        'remove_effect' => __('The captcha check stops until a new secret key is saved. Forms keep the honeypot.', 'corex'),
                        'show_for'      => ['key' => 'captcha.driver', 'values' => ['recaptcha', 'turnstile', 'hcaptcha']],
                        'help_variants' => [
                            'recaptcha' => [
                                'help'      => __('The private reCAPTCHA secret key.', 'corex'),
                                'help_url'  => 'https://www.google.com/recaptcha/admin/create',
                                'help_link' => __('Create reCAPTCHA keys', 'corex'),
                            ],
                            'turnstile' => [
                                'help'      => __('The private Cloudflare Turnstile secret key.', 'corex'),
                                'help_url'  => 'https://developers.cloudflare.com/turnstile/get-started/',
                                'help_link' => __('Get Turnstile keys', 'corex'),
                            ],
                            'hcaptcha' => [
                                'help'      => __('The private hCaptcha secret key.', 'corex'),
                                'help_url'  => 'https://docs.hcaptcha.com/#integration-testing-test-keys',
                                'help_link' => __('hCaptcha secret key docs', 'corex'),
                            ],
                        ],
                    ],
                    'captcha.score_threshold' => [
                        'label'     => __('reCAPTCHA v3 score threshold', 'corex'),
                        'type'      => 'text',
                        'help'      => __('0.0–1.0. Defaults to 0.3 — conservative for ordinary low-traffic sites. Watch real traffic before raising it; a higher threshold rejects more legitimate visitors. Only protected CoreX forms are covered.', 'corex'),
                        'help_url'  => 'https://developers.google.com/recaptcha/docs/v3#interpreting_the_score',
                        'help_link' => __('reCAPTCHA v3 score docs', 'corex'),
                        'show_for'  => ['key' => 'captcha.driver', 'values' => ['recaptcha']],
                    ],
                    'captcha.action' => [
                        'label'     => __('reCAPTCHA v3 action (global default)', 'corex'),
                        'type'      => 'text',
                        'help'      => __('Optional. Each form derives its own action from its slug (corex_form_<slug>); a form can override it. Leave blank unless you need one shared action.', 'corex'),
                        'help_url'  => 'https://developers.google.com/recaptcha/docs/v3#actions',
                        'help_link' => __('reCAPTCHA v3 action docs', 'corex'),
                        'show_for'  => ['key' => 'captcha.driver', 'values' => ['recaptcha']],
                    ],
                    'captcha.allowed_hostnames' => [
                        'label'     => __('Allowed hostnames', 'corex'),
                        'type'      => 'text',
                        'help'      => __('Comma-separated exact hostnames a verification may originate from (e.g. example.com, staging.example.com). Leave blank to allow this site’s own host only. Matched exactly, never by partial match. The secret key never reaches the browser.', 'corex'),
                        'show_for'  => ['key' => 'captcha.driver', 'values' => ['recaptcha']],
                    ],
                ],
            ],
            'media' => [
                'title'  => __('Media', 'corex'),
                'fields' => [
                    'media.webp.support' => [
                        'label' => __('Server support', 'corex'),
                        'type'  => 'info',
                        'help'  => __('Live image-support on this server. Also reported under Tools → Site Health.', 'corex'),
                    ],
                    'media.webp.enabled' => [
                        'label' => __('Convert uploads to WebP', 'corex'),
                        'type'  => 'checkbox',
                        'help'  => __('On upload, write a WebP sibling next to the original. Originals are always kept.', 'corex'),
                    ],
                    'media.webp.quality' => [
                        'label' => __('WebP quality', 'corex'),
                        'type'  => 'text',
                        'help'  => __('1–100. 82 is a good balance of size and quality.', 'corex'),
                    ],
                    'media.webp.min_saving' => [
                        'label' => __('Minimum size saving (%)', 'corex'),
                        'type'  => 'text',
                        'help'  => __('A WebP is only served when it is at least this much smaller than the original. Default 5.', 'corex'),
                    ],
                    'media.webp.convert_jpeg' => [
                        'label' => __('Convert JPEG uploads', 'corex'),
                        'type'  => 'checkbox',
                    ],
                    'media.webp.convert_png' => [
                        'label' => __('Convert PNG uploads', 'corex'),
                        'type'  => 'checkbox',
                    ],
                ],
            ],
            'insights' => [
                'title'  => __('Insights', 'corex'),
                'fields' => [
                    'insights.psi.key' => [
                        'label'     => __('PageSpeed Insights API key', 'corex'),
                        'type'      => 'password',
                        'remove_effect' => __('Performance checks go back to Google\'s shared keyless quota, which is refused when it runs out.', 'corex'),
                        'help'      => __('Used for performance checks.', 'corex'),
                        'help_url'  => 'https://developers.google.com/speed/docs/insights/v5/get-started',
                        'help_link' => __('Get a PageSpeed Insights API key', 'corex'),
                    ],
                    'insights.cloudflare.token' => [
                        'label'     => __('Cloudflare API token', 'corex'),
                        'type'      => 'password',
                        'remove_effect' => __('The Cloudflare security scan stops until a new token is saved.', 'corex'),
                        'help'      => __('Used for the security/readiness scan.', 'corex'),
                        'help_url'  => 'https://developers.cloudflare.com/fundamentals/api/get-started/create-token/',
                        'help_link' => __('Create a Cloudflare API token', 'corex'),
                    ],
                    'insights.cloudflare.account_id' => [
                        'label'     => __('Cloudflare account ID', 'corex'),
                        'type'      => 'text',
                        'help'      => __('Found in the Cloudflare dashboard under your account home / API section.', 'corex'),
                        'help_url'  => 'https://developers.cloudflare.com/fundamentals/setup/find-account-and-zone-ids/',
                        'help_link' => __('Find your Cloudflare account ID', 'corex'),
                    ],
                ],
            ],
            // The optional Dashboard widgets (spec 072 US7 / FR-025). Opt-in by definition, so every
            // field here defaults to off; the Command Center widget is not listed because it is
            // registered for everyone with CoreX visibility (FR-023) and is not optional.
            'dashboard' => [
                'title'  => __('Dashboard', 'corex'),
                'fields' => [
                    'dashboard.widgets.attention' => [
                        'label' => __('Attention widget', 'corex'),
                        'type'  => 'checkbox',
                        'help'  => __('Lists your unread CoreX notifications on the WordPress dashboard. Hidden automatically when you have none.', 'corex'),
                    ],
                    'dashboard.widgets.development' => [
                        'label' => __('Development widget', 'corex'),
                        'type'  => 'checkbox',
                        'help'  => __('Shows the operating mode and its warnings. Only ever appears while the site is in Development.', 'corex'),
                    ],
                ],
            ],
            // The Guides add-on's support contact (spec 087, FR-003). Declared here for the same
            // reason the `captcha` section is: a Config dot-key is a string, and the screen that
            // edits it does not have to be the plugin that reads it. The default lives in
            // `addons/corex-guides/config/guides.php`; saving here writes the option that layers
            // over it, so a blank field means "use the shipped address", not "no address".
            'guides' => [
                'title'  => __('Guides', 'corex'),
                'fields' => [
                    'guides.support.email' => [
                        'label' => __('Support email', 'corex'),
                        'type'  => 'email',
                        'help'  => __('Where "Still stuck?" messages from the Guides screen are sent. Leave blank to use the address the add-on ships with.', 'corex'),
                    ],
                    'guides.support.enabled' => [
                        'label' => __('Offer the support form', 'corex'),
                        'type'  => 'checkbox',
                        'help'  => __('Turn off to remove the contact panel from the Guides screen entirely.', 'corex'),
                    ],
                ],
            ],
            // Advanced is a read-only system-diagnostics read-out (spec 068 T203). Destructive resets
            // live behind their own typed-confirmation surfaces (Operations & Security, Setup Wizard);
            // this section never fabricates a value. Operations, Data Sources, and Design Tokens keep
            // their dedicated screens rather than being duplicated here.
            'advanced' => [
                'title'  => __('Advanced', 'corex'),
                'fields' => [
                    'advanced.php_version'  => ['label' => __('PHP version', 'corex'), 'type' => 'info', 'help' => __('The PHP runtime this site is on.', 'corex')],
                    'advanced.wp_version'   => ['label' => __('WordPress version', 'corex'), 'type' => 'info'],
                    'advanced.environment'  => ['label' => __('Environment type', 'corex'), 'type' => 'info'],
                    'advanced.memory_limit' => ['label' => __('PHP memory limit', 'corex'), 'type' => 'info'],
                    'advanced.multisite'    => ['label' => __('Multisite', 'corex'), 'type' => 'info'],
                ],
            ],
        ];
    }

    /**
     * The site's pages, for a choice of one, by id, drafts included so a draft can be chosen and
     * published later. Read only where WordPress is loaded; the declaration stays readable without.
     *
     * @return array<string,string>
     */
    private function pageChoices(): array
    {
        $choices = ['' => __('None chosen', 'corex')];

        if (! function_exists('get_pages')) {
            return $choices;
        }

        foreach ((array) get_pages(['post_status' => 'publish,draft,pending,private', 'sort_column' => 'post_title']) as $page) {
            if ($page instanceof \WP_Post) {
                $choices[(string) $page->ID] = $page->post_title !== '' ? $page->post_title : '#' . $page->ID;
            }
        }

        return $choices;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [];

        foreach ($this->sections() as $section) {
            foreach (array_keys($section['fields']) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * The write-only secret keys (password-typed fields): captcha secret, API tokens.
     * The settings save loop preserves the stored value when one of these is submitted
     * empty, so a write-only field is never cleared by re-saving the form (spec 060 / M6).
     *
     * @return list<string>
     */
    public function secretKeys(): array
    {
        $keys = [];

        foreach ($this->sections() as $section) {
            foreach ($section['fields'] as $key => $field) {
                if (($field['type'] ?? '') === 'password') {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }
}
