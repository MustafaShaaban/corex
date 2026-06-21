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
                    'brand.logo_url'    => ['label' => 'Admin logo', 'type' => 'media'],
                    'brand.footer_text' => ['label' => 'Admin footer text', 'type' => 'text'],
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
                    ],
                    'captcha.site_key'        => ['label' => 'Site key', 'type' => 'text'],
                    'captcha.secret'          => ['label' => 'Secret key', 'type' => 'password'],
                    'captcha.score_threshold' => ['label' => 'reCAPTCHA v3 score threshold', 'type' => 'text'],
                    'captcha.action'          => ['label' => 'reCAPTCHA v3 action', 'type' => 'text'],
                ],
            ],
            'insights' => [
                'title'  => 'Insights',
                'fields' => [
                    'insights.psi.key'              => ['label' => 'PageSpeed Insights API key', 'type' => 'password'],
                    'insights.cloudflare.token'     => ['label' => 'Cloudflare API token', 'type' => 'password'],
                    'insights.cloudflare.account_id' => ['label' => 'Cloudflare account ID', 'type' => 'text'],
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
