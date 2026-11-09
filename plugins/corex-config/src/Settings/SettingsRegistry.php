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
            // The Assistant add-on (spec 129), declared here for the reason the `captcha` section is.
            'assistant' => [
                'title'  => __('Assistant', 'corex'),
                'fields' => $this->assistantFields(),
            ],
            'assistant_services' => [
                'title'  => __('Services', 'corex'),
                'fields' => $this->assistantServiceFields(),
            ],
            'assistant_booking' => [
                'title'  => __('Booking', 'corex'),
                'fields' => $this->assistantBookingFields(),
            ],
            'assistant_limits' => [
                'title'  => __('Limits', 'corex'),
                'fields' => $this->assistantLimitFields(),
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
     * The Assistant's own fields (spec 129, FR-006, FR-025): the switch, its name and image, and for
     * each of the site's languages the name, welcome and suggested questions the owner writes.
     *
     * A field may carry `guide_label`: the words the Settings guide names it by, where its label
     * changes from site to site (one per language, one per service).
     *
     * @return array<string,array<string,mixed>>
     */
    private function assistantFields(): array
    {
        $fields = [
            'assistant.enabled' => [
                'label' => __('Show the assistant', 'corex'),
                'type'  => 'checkbox',
                'help'  => __('A chat button on every public page. It shows once at least one service under Services is on and has a key.', 'corex'),
            ],
            'assistant.name' => [
                'label' => __('Assistant name', 'corex'),
                'type'  => 'text',
                'help'  => __('What the chat calls itself in every language without a name of its own below. Leave blank for your site\'s name followed by "Assistant".', 'corex'),
            ],
            'assistant.mark' => [
                'label' => __('Assistant image', 'corex'),
                'type'  => 'media',
                'help'  => __('Shown on the round button and beside the assistant\'s name, at about 32 pixels. A square image with a transparent background works best. Leave blank for a speech-bubble icon.', 'corex'),
            ],
        ];

        foreach ($this->assistantLanguages() as $code => $language) {
            $prefix = 'assistant.brand.' . $code . '.';
            $fields[$prefix . 'name'] = [
                /* translators: %s: a language, such as Arabic. */
                'label'       => sprintf(__('Name in %s', 'corex'), $language),
                'guide_label' => __('Name in each language', 'corex'),
                'type'        => 'text',
                'help'        => __('Leave blank for the Assistant name above.', 'corex'),
            ];
            $fields[$prefix . 'welcome'] = [
                /* translators: %s: a language, such as Arabic. */
                'label'       => sprintf(__('Welcome in %s', 'corex'), $language),
                'guide_label' => __('Welcome in each language', 'corex'),
                'type'        => 'text',
                'help'        => __('The first thing the chat says. Leave blank for the built-in welcome.', 'corex'),
            ];
            foreach ([1, 2, 3] as $number) {
                $fields[$prefix . 'suggestion' . $number] = [
                    /* translators: 1: 1, 2 or 3. 2: a language, such as Arabic. */
                    'label'       => sprintf(__('Suggested question %1$d in %2$s', 'corex'), $number, $language),
                    'guide_label' => __('Suggested questions in each language', 'corex'),
                    'type'        => 'text',
                    'help'        => __('A question a visitor can send with one tap. Leave all three blank for the built-in ones.', 'corex'),
                ];
            }
        }

        return $fields;
    }

    /**
     * The services (spec 129, FR-006, FR-016 to FR-018): the order the assistant asks them in, and
     * for each a switch, a key and a model, then the cap on paid answers. The services are named
     * here rather than read from the add-on, so the screen does not need it loaded. Mirrors
     * `Corex\Assistant\Chain\Provider::CATALOGUE`.
     *
     * @return array<string,array<string,mixed>>
     */
    private function assistantServiceFields(): array
    {
        $services = [
            'gemini'     => ['Google Gemini', 'https://aistudio.google.com/apikey', 'gemini-flash-lite-latest', false],
            'groq'       => ['Groq', 'https://console.groq.com/keys', 'openai/gpt-oss-120b', false],
            'openrouter' => ['OpenRouter', 'https://openrouter.ai/settings/keys', 'openrouter/free', false],
            'mistral'    => ['Mistral', 'https://console.mistral.ai/api-keys', 'mistral-small-latest', false],
            'openai'     => ['OpenAI', 'https://platform.openai.com/api-keys', 'gpt-5-mini', true],
            'anthropic'  => ['Anthropic Claude', 'https://platform.claude.com/settings/keys', 'claude-haiku-5-5', true],
        ];

        $fields = [
            'assistant.order' => [
                'label'   => __('Order the services are asked in', 'corex'),
                'type'    => 'order',
                'options' => array_map(static fn (array $service): string => $service[0], $services),
                'help'    => __('The first service that is on and has a key answers; while it rests, the next one does. Free services first keeps the cost at nothing.', 'corex'),
            ],
        ];

        foreach ($services as $id => [$label, $keysUrl, $model, $paid]) {
            $fields['assistant.' . $id . '.on'] = [
                /* translators: %s: an AI service, such as Groq. */
                'label'       => sprintf(__('Ask %s', 'corex'), $label),
                'guide_label' => __('Ask, one switch per service', 'corex'),
                'type'        => 'checkbox',
                'help'        => $paid
                    ? __('A paid service: each answer it gives is charged to your account with it, up to the paid answers a day below. Off until you switch it on.', 'corex')
                    : __('A free service. Off, it is skipped even with a key.', 'corex'),
            ];
            $fields['assistant.' . $id . '.key'] = [
                /* translators: %s: an AI service, such as Groq. */
                'label'         => sprintf(__('%s API key', 'corex'), $label),
                'type'          => 'password',
                'remove_effect' => __('The assistant stops asking this service. It keeps asking the others that are on and have a key.', 'corex'),
                'help'          => $paid
                    ? __('A key from a paid account. Leave blank to never use this service.', 'corex')
                    : __('A free key works. Leave the other services blank if you have only one.', 'corex'),
                'help_url'      => $keysUrl,
                /* translators: %s: an AI service, such as Groq. */
                'help_link'     => sprintf(__('Get a %s key', 'corex'), $label),
            ];
            $fields['assistant.' . $id . '.model'] = [
                /* translators: %s: an AI service, such as Groq. */
                'label' => sprintf(__('%s model', 'corex'), $label),
                'type'  => 'text',
                /* translators: %s: a model name, such as mistral-small-latest. */
                'help'  => sprintf(__('Leave blank for %s. Change it only when the service retires that model.', 'corex'), $model),
            ];
        }

        $fields['assistant.paid_cap'] = [
            'label' => __('Paid answers a day', 'corex'),
            'type'  => 'number',
            'help'  => __('Past this, the paid services wait until the next day (UTC) and only the free ones answer. 0 never uses them. Default 50.', 'corex'),
        ];

        return $fields;
    }

    /**
     * Booking hours (spec 129, FR-020): the times the callback form offers a visitor for a call,
     * inside the team's hours, in the team's time zone. A requested time the team confirms.
     *
     * @return array<string,array<string,mixed>>
     */
    private function assistantBookingFields(): array
    {
        $shownWhenOn = ['key' => 'assistant.booking.enabled', 'values' => ['1']];
        $times       = [];
        for ($minutes = 0; $minutes < 1440; $minutes += 30) {
            $time         = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            $times[$time] = $time;
        }
        $horizons = [];
        foreach ([1, 2, 3, 5, 7, 10, 14, 21, 30] as $days) {
            /* translators: %d: a number of days, 2 or more. */
            $horizons[(string) $days] = $days === 1 ? __('1 day', 'corex') : sprintf(__('%d days', 'corex'), $days);
        }

        return [
            'assistant.booking.enabled' => [
                'label' => __('Offer call times', 'corex'),
                'type'  => 'checkbox',
                'help'  => __('The callback form offers times inside your hours, shown in the visitor\'s own time zone. Off, it asks for the best time in their words.', 'corex'),
            ],
            'assistant.booking.timezone' => [
                'label'    => __('Your time zone', 'corex'),
                'type'     => 'select',
                'options'  => ['' => __('The site\'s time zone (Settings, General)', 'corex')] + self::timeZones(),
                'show_for' => $shownWhenOn,
            ],
            'assistant.booking.days' => [
                'label'    => __('Days you take calls', 'corex'),
                'type'     => 'select',
                'options'  => [
                    'every'   => __('Every day', 'corex'),
                    'mon-fri' => __('Monday to Friday', 'corex'),
                    'mon-sat' => __('Monday to Saturday', 'corex'),
                    'sun-thu' => __('Sunday to Thursday', 'corex'),
                    'sat-thu' => __('Saturday to Thursday', 'corex'),
                ],
                'show_for' => $shownWhenOn,
            ],
            'assistant.booking.start' => [
                'label'    => __('First call at', 'corex'),
                'type'     => 'select',
                'options'  => $times,
                'show_for' => $shownWhenOn,
            ],
            'assistant.booking.end' => [
                'label'    => __('Calls end at', 'corex'),
                'type'     => 'select',
                'options'  => $times,
                'help'     => __('A time at or before the first call runs past midnight into the next day.', 'corex'),
                'show_for' => $shownWhenOn,
            ],
            'assistant.booking.slot' => [
                'label'    => __('Each call lasts', 'corex'),
                'type'     => 'select',
                'options'  => self::durations([15, 20, 30, 45, 60, 90, 120]),
                'show_for' => $shownWhenOn,
            ],
            'assistant.booking.notice' => [
                'label'    => __('Earliest call, from now', 'corex'),
                'type'     => 'select',
                'options'  => ['0' => __('Right away', 'corex')] + self::durations([30, 60, 120, 240, 1440, 2880]),
                'show_for' => $shownWhenOn,
            ],
            'assistant.booking.horizon' => [
                'label'    => __('Days a visitor can choose from', 'corex'),
                'type'     => 'select',
                'options'  => $horizons,
                'show_for' => $shownWhenOn,
            ],
        ];
    }

    /**
     * The limits (spec 129, FR-008, FR-024): what one visitor and the whole site may ask.
     *
     * @return array<string,array<string,mixed>>
     */
    private function assistantLimitFields(): array
    {
        return [
            'assistant.limit.hour' => [
                'label' => __('Questions an hour, each visitor', 'corex'),
                'type'  => 'number',
                'help'  => __('Past this, that visitor is offered the callback form until the hour is over. Default 20.', 'corex'),
            ],
            'assistant.limit.day' => [
                'label' => __('Questions a day, each visitor', 'corex'),
                'type'  => 'number',
                'help'  => __('Default 60.', 'corex'),
            ],
            'assistant.daily_cap' => [
                'label' => __('Questions a day, whole site', 'corex'),
                'type'  => 'number',
                'help'  => __('Past this, the chat offers the callback form instead of answering until the next day (UTC). Default 600.', 'corex'),
            ],
        ];
    }

    /**
     * The languages the site writes in, by the two- or three-letter code the assistant keys its
     * words by: the site's own language and every installed language pack, narrowed or widened by
     * the `corex_assistant_languages` filter (a list of codes). At most six.
     *
     * @return array<string,string> code => the language's name
     */
    private function assistantLanguages(): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            [self::class, 'languageCode'],
            [self::siteLocale(), ...self::installedLocales()],
        ))));
        $asked = function_exists('apply_filters') ? apply_filters('corex_assistant_languages', $codes) : $codes;

        $languages = [];
        foreach (is_array($asked) ? $asked : [] as $code) {
            $code = self::languageCode(is_string($code) ? $code : '');
            if ($code !== '') {
                $languages[$code] = self::languageName($code);
            }
        }

        return array_slice($languages, 0, 6, true);
    }

    /** A locale as the language code the assistant keys its words by (`ar_EG` is `ar`), or ''. */
    private static function languageCode(string $locale): string
    {
        $code = strtolower((string) strtok(trim($locale), '_-'));

        return preg_match('/^[a-z]{2,3}$/', $code) === 1 ? $code : '';
    }

    /**
     * The site's locale as WordPress set it while it loaded (`wp-settings.php` assigns the global
     * from `get_locale()`). Read from there rather than asked again, so the declaration calls no
     * WordPress function and reads the same in a test as on a site.
     */
    private static function siteLocale(): string
    {
        $locale = $GLOBALS['locale'] ?? 'en_US';

        return is_string($locale) && $locale !== '' ? $locale : 'en_US';
    }

    /**
     * The language packs installed for WordPress itself, as `get_available_languages()` lists them:
     * the `.mo` files in the languages folder, less the time zone and admin-only ones.
     *
     * @return list<string> locales, such as ar or pt_BR
     */
    private static function installedLocales(): array
    {
        $files = defined('WP_LANG_DIR') ? glob(WP_LANG_DIR . '/*.mo') : [];
        $locales = [];
        foreach (is_array($files) ? $files : [] as $file) {
            $locale = basename($file, '.mo');
            if (! str_starts_with($locale, 'continents-cities') && ! str_starts_with($locale, 'ms-') && ! str_starts_with($locale, 'admin-')) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    /** A language's name in the site's own language, from PHP's intl when it is there. */
    private static function languageName(string $code): string
    {
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayLanguage($code, self::siteLocale());
            if ($name !== '' && $name !== $code) {
                return $name;
            }
        }

        return strtoupper($code);
    }

    /**
     * Durations for a select, keyed by their number of minutes.
     *
     * @param list<int> $minutes
     *
     * @return array<string,string>
     */
    private static function durations(array $minutes): array
    {
        $names = [
            15   => __('15 minutes', 'corex'),
            20   => __('20 minutes', 'corex'),
            30   => __('30 minutes', 'corex'),
            45   => __('45 minutes', 'corex'),
            60   => __('1 hour', 'corex'),
            90   => __('1 hour 30 minutes', 'corex'),
            120  => __('2 hours', 'corex'),
            240  => __('4 hours', 'corex'),
            1440 => __('1 day', 'corex'),
            2880 => __('2 days', 'corex'),
        ];

        $choices = [];
        foreach ($minutes as $count) {
            $choices[(string) $count] = $names[$count];
        }

        return $choices;
    }

    /**
     * The world's time zones by their names, such as Africa/Cairo.
     *
     * @return array<string,string>
     */
    private static function timeZones(): array
    {
        $zones = \DateTimeZone::listIdentifiers();

        return array_combine($zones, $zones);
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
