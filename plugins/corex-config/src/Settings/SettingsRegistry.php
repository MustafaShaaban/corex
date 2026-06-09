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
final class SettingsRegistry
{
    /**
     * @return array<string,array{title:string,fields:array<string,array{label:string,type:string}>}>
     */
    public function sections(): array
    {
        return [
            'brand' => [
                'title'  => 'Brand',
                'fields' => [
                    'brand.logo_url'    => ['label' => 'Admin logo URL', 'type' => 'url'],
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
                    'captcha.driver' => ['label' => 'Captcha driver (none/honeypot/turnstile/...)', 'type' => 'text'],
                    'captcha.secret' => ['label' => 'Captcha secret', 'type' => 'password'],
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
}
