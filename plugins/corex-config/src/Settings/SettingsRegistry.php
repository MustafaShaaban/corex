<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * Declares the configurable Corex settings — sections and fields. Pure. Each field
 * key is a Config dot-key, so saving it persists to the option the Config engine reads.
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
                'title'  => 'Brand',
                'fields' => [
                    'brand.company_name' => [
                        'label' => 'Company name',
                        'type'  => 'text',
                        'help'  => 'Used across the site chrome, footer, and the Setup Wizard brand step.',
                    ],
                    'brand.tagline' => [
                        'label' => 'Tagline',
                        'type'  => 'text',
                    ],
                    'brand.phone' => [
                        'label' => 'Contact phone',
                        'type'  => 'tel',
                    ],
                    'brand.email' => [
                        'label' => 'Contact email',
                        'type'  => 'email',
                    ],
                    'brand.address' => [
                        'label' => 'Address',
                        'type'  => 'text',
                    ],
                    'brand.primary_action_label' => [
                        'label' => 'Primary action label',
                        'type'  => 'text',
                        'help'  => 'The call-to-action button label used by kit patterns (e.g. "Get a quote").',
                    ],
                    'brand.primary_action_link' => [
                        'label' => 'Primary action link',
                        'type'  => 'url',
                    ],
                    'brand.social_links' => [
                        'label' => 'Social links',
                        'type'  => 'text',
                        'help'  => 'Comma-separated profile URLs.',
                    ],
                    'brand.logo_url'    => [
                        'label' => 'Admin logo',
                        'type'  => 'media',
                        'help'  => 'Used on the CoreX admin shell and the WordPress login screen.',
                    ],
                    'brand.footer_text' => [
                        'label' => 'Admin footer text',
                        'type'  => 'text',
                        'help'  => 'Replaces the wp-admin footer text. Leave blank for "Powered by Corex".',
                    ],
                    'brand.admin_appearance' => [
                        'label'   => 'CoreX admin appearance',
                        'type'    => 'select',
                        'options' => ['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'],
                        'help'    => 'System follows your operating-system colour scheme.',
                    ],
                    'brand.login_sso_enabled' => [
                        'label' => 'Enable SSO login section',
                        'type'  => 'checkbox',
                        'help'  => 'Reserves a single sign-on slot on the login screen. No provider is configured yet.',
                    ],
                ],
            ],
            'mail' => [
                'title'  => 'Mail',
                'fields' => [
                    'mail.from.name'    => ['label' => 'From name', 'type' => 'text'],
                    'mail.from.address' => ['label' => 'From address', 'type' => 'email'],
                    'mail.reply_to'     => [
                        'label' => __('Default reply-to address', 'corex'),
                        'type'  => 'email',
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
                ],
            ],
            'forms' => [
                'title'  => 'Forms',
                'fields' => [
                    'forms.email.recipient' => ['label' => 'Form notification recipient', 'type' => 'email'],
                ],
            ],
            'captcha' => [
                'title'  => 'Captcha',
                'fields' => [
                    'captcha.driver' => [
                        'label'   => 'Captcha driver',
                        'type'    => 'select',
                        'options' => [
                            'none'      => 'None',
                            'honeypot'  => 'Honeypot',
                            'recaptcha' => 'reCAPTCHA',
                            'turnstile' => 'Cloudflare Turnstile',
                            'hcaptcha'  => 'hCaptcha',
                        ],
                        'help' => 'Choose a provider, Honeypot (no keys), or None to disable.',
                    ],
                    'captcha.site_key' => [
                        'label'         => 'Site key',
                        'type'          => 'text',
                        'show_for'      => ['key' => 'captcha.driver', 'values' => ['recaptcha', 'turnstile', 'hcaptcha']],
                        'help_variants' => [
                            'recaptcha' => [
                                'help'      => 'The public reCAPTCHA site key.',
                                'help_url'  => 'https://www.google.com/recaptcha/admin/create',
                                'help_link' => 'Create reCAPTCHA keys',
                            ],
                            'turnstile' => [
                                'help'      => 'The public Cloudflare Turnstile site key.',
                                'help_url'  => 'https://developers.cloudflare.com/turnstile/get-started/',
                                'help_link' => 'Get Turnstile keys',
                            ],
                            'hcaptcha' => [
                                'help'      => 'The public hCaptcha site key.',
                                'help_url'  => 'https://docs.hcaptcha.com/#add-the-hcaptcha-widget-to-your-webpage',
                                'help_link' => 'hCaptcha site key docs',
                            ],
                        ],
                    ],
                    'captcha.secret' => [
                        'label'         => 'Secret key',
                        'type'          => 'password',
                        'show_for'      => ['key' => 'captcha.driver', 'values' => ['recaptcha', 'turnstile', 'hcaptcha']],
                        'help_variants' => [
                            'recaptcha' => [
                                'help'      => 'The private reCAPTCHA secret key.',
                                'help_url'  => 'https://www.google.com/recaptcha/admin/create',
                                'help_link' => 'Create reCAPTCHA keys',
                            ],
                            'turnstile' => [
                                'help'      => 'The private Cloudflare Turnstile secret key.',
                                'help_url'  => 'https://developers.cloudflare.com/turnstile/get-started/',
                                'help_link' => 'Get Turnstile keys',
                            ],
                            'hcaptcha' => [
                                'help'      => 'The private hCaptcha secret key.',
                                'help_url'  => 'https://docs.hcaptcha.com/#integration-testing-test-keys',
                                'help_link' => 'hCaptcha secret key docs',
                            ],
                        ],
                    ],
                    'captcha.score_threshold' => [
                        'label'     => 'reCAPTCHA v3 score threshold',
                        'type'      => 'text',
                        'help'      => '0.0–1.0. Defaults to 0.3 — conservative for ordinary low-traffic sites. Watch real traffic before raising it; a higher threshold rejects more legitimate visitors. Only protected CoreX forms are covered.',
                        'help_url'  => 'https://developers.google.com/recaptcha/docs/v3#interpreting_the_score',
                        'help_link' => 'reCAPTCHA v3 score docs',
                        'show_for'  => ['key' => 'captcha.driver', 'values' => ['recaptcha']],
                    ],
                    'captcha.action' => [
                        'label'     => 'reCAPTCHA v3 action (global default)',
                        'type'      => 'text',
                        'help'      => 'Optional. Each form derives its own action from its slug (corex_form_<slug>); a form can override it. Leave blank unless you need one shared action.',
                        'help_url'  => 'https://developers.google.com/recaptcha/docs/v3#actions',
                        'help_link' => 'reCAPTCHA v3 action docs',
                        'show_for'  => ['key' => 'captcha.driver', 'values' => ['recaptcha']],
                    ],
                    'captcha.allowed_hostnames' => [
                        'label'     => 'Allowed hostnames',
                        'type'      => 'text',
                        'help'      => 'Comma-separated exact hostnames a verification may originate from (e.g. example.com, staging.example.com). Leave blank to allow this site’s own host only. Matched exactly, never by partial match. The secret key never reaches the browser.',
                        'show_for'  => ['key' => 'captcha.driver', 'values' => ['recaptcha']],
                    ],
                ],
            ],
            'media' => [
                'title'  => 'Media',
                'fields' => [
                    'media.webp.support' => [
                        'label' => 'Server support',
                        'type'  => 'info',
                        'help'  => 'Live image-support on this server. Also reported under Tools → Site Health.',
                    ],
                    'media.webp.enabled' => [
                        'label' => 'Convert uploads to WebP',
                        'type'  => 'checkbox',
                        'help'  => 'On upload, write a WebP sibling next to the original. Originals are always kept.',
                    ],
                    'media.webp.quality' => [
                        'label' => 'WebP quality',
                        'type'  => 'text',
                        'help'  => '1–100. 82 is a good balance of size and quality.',
                    ],
                    'media.webp.min_saving' => [
                        'label' => 'Minimum size saving (%)',
                        'type'  => 'text',
                        'help'  => 'A WebP is only served when it is at least this much smaller than the original. Default 5.',
                    ],
                    'media.webp.convert_jpeg' => [
                        'label' => 'Convert JPEG uploads',
                        'type'  => 'checkbox',
                    ],
                    'media.webp.convert_png' => [
                        'label' => 'Convert PNG uploads',
                        'type'  => 'checkbox',
                    ],
                ],
            ],
            'insights' => [
                'title'  => 'Insights',
                'fields' => [
                    'insights.psi.key' => [
                        'label'     => 'PageSpeed Insights API key',
                        'type'      => 'password',
                        'help'      => 'Used for performance checks.',
                        'help_url'  => 'https://developers.google.com/speed/docs/insights/v5/get-started',
                        'help_link' => 'Get a PageSpeed Insights API key',
                    ],
                    'insights.cloudflare.token' => [
                        'label'     => 'Cloudflare API token',
                        'type'      => 'password',
                        'help'      => 'Used for the security/readiness scan.',
                        'help_url'  => 'https://developers.cloudflare.com/fundamentals/api/get-started/create-token/',
                        'help_link' => 'Create a Cloudflare API token',
                    ],
                    'insights.cloudflare.account_id' => [
                        'label'     => 'Cloudflare account ID',
                        'type'      => 'text',
                        'help'      => 'Found in the Cloudflare dashboard under your account home / API section.',
                        'help_url'  => 'https://developers.cloudflare.com/fundamentals/setup/find-account-and-zone-ids/',
                        'help_link' => 'Find your Cloudflare account ID',
                    ],
                ],
            ],
            // The optional Dashboard widgets (spec 072 US7 / FR-025). Opt-in by definition, so every
            // field here defaults to off; the Command Center widget is not listed because it is
            // registered for everyone with CoreX visibility (FR-023) and is not optional.
            'dashboard' => [
                'title'  => 'Dashboard',
                'fields' => [
                    'dashboard.widgets.attention' => [
                        'label' => 'Attention widget',
                        'type'  => 'checkbox',
                        'help'  => 'Lists your unread CoreX notifications on the WordPress dashboard. Hidden automatically when you have none.',
                    ],
                    'dashboard.widgets.development' => [
                        'label' => 'Development widget',
                        'type'  => 'checkbox',
                        'help'  => 'Shows the operating mode and its warnings. Only ever appears while the site is in Development.',
                    ],
                ],
            ],
            // Advanced is a read-only system-diagnostics read-out (spec 068 T203). Destructive resets
            // live behind their own typed-confirmation surfaces (Operations & Security, Setup Wizard);
            // this section never fabricates a value. Operations, Data Sources, and Design Tokens keep
            // their dedicated screens rather than being duplicated here.
            'advanced' => [
                'title'  => 'Advanced',
                'fields' => [
                    'advanced.php_version'  => ['label' => 'PHP version', 'type' => 'info', 'help' => 'The PHP runtime this site is on.'],
                    'advanced.wp_version'   => ['label' => 'WordPress version', 'type' => 'info'],
                    'advanced.environment'  => ['label' => 'Environment type', 'type' => 'info'],
                    'advanced.memory_limit' => ['label' => 'PHP memory limit', 'type' => 'info'],
                    'advanced.multisite'    => ['label' => 'Multisite', 'type' => 'info'],
                ],
            ],
        ];
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
