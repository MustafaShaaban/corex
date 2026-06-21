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
                        'label'     => 'Site key',
                        'type'      => 'text',
                        'help'      => 'The public key for your provider.',
                        'help_url'  => 'https://www.google.com/recaptcha/admin/create',
                        'help_link' => 'Create reCAPTCHA keys',
                    ],
                    'captcha.secret' => [
                        'label'     => 'Secret key',
                        'type'      => 'password',
                        'help'      => 'The private key, paired with the site key.',
                        'help_url'  => 'https://www.google.com/recaptcha/admin/create',
                        'help_link' => 'Create reCAPTCHA keys',
                    ],
                    'captcha.score_threshold' => [
                        'label'     => 'reCAPTCHA v3 score threshold',
                        'type'      => 'text',
                        'help'      => '0.0–1.0. 0.5 is a common starting point — adjust after reviewing traffic.',
                        'help_url'  => 'https://developers.google.com/recaptcha/docs/v3#interpreting_the_score',
                        'help_link' => 'reCAPTCHA v3 score docs',
                    ],
                    'captcha.action' => [
                        'label'     => 'reCAPTCHA v3 action',
                        'type'      => 'text',
                        'help'      => 'A label for the protected action, e.g. contact_form or login.',
                        'help_url'  => 'https://developers.google.com/recaptcha/docs/v3#actions',
                        'help_link' => 'reCAPTCHA v3 action docs',
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
