<?php

/**
 * Plugin Name: CoreX E2E assistant
 * Description: Lets the browser suite drive the CoreX Assistant as a site with it on and keyed, with a stand-in model.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Answers for one request (`assistant.spec.js`, spec 129).
 *
 * The assistant is off on the suite's site, and turning it on there would put a chat button over every
 * page another spec reads. A request carrying the header `X-Corex-E2E-Assistant: on` is answered as a
 * site with the assistant on and one service keyed, and that service's answers come from here: nothing
 * leaves the machine. A question naming a price asks for the team; any other is answered with the
 * page this file gives as the assistant's knowledge and an address elsewhere, which the assistant must
 * drop. The visitor's counts and lock are cleared on every such request, so repeated runs never meet the
 * hourly limit. `X-Corex-E2E-Assistant: booking` is the same with booking hours on as well (every
 * day round the clock, an hour a call, the next two days, UTC), so the hand-off offers times (spec 129
 * slice 2); a hand-off sent with it is answered but neither stored nor emailed. Without the header
 * this file does nothing. No setting is stored: each is answered
 * through its `pre_option_` filter.
 *
 * An mu-plugin so it is active with no activation step. How the fixtures reach an install is in
 * `corex-e2e-core-notices.php`.
 */
add_action('plugins_loaded', static function (): void {
    $mode = isset($_SERVER['HTTP_X_COREX_E2E_ASSISTANT'])
        ? sanitize_key(wp_unslash((string) $_SERVER['HTTP_X_COREX_E2E_ASSISTANT']))
        : '';
    if (! in_array($mode, ['on', 'booking'], true)) {
        return;
    }

    if ($mode === 'booking') {
        foreach ([
            'enabled' => '1',
            'days' => 'every',
            'start' => '00:00',
            'end' => '00:00',
            'slot' => '60',
            'notice' => '0',
            'horizon' => '2',
            'timezone' => 'UTC',
        ] as $field => $value) {
            add_filter('pre_option_corex_assistant_booking_' . $field, static fn (): string => $value);
        }
        // A hand-off sent here is checked, prepared and answered, and then neither stored nor emailed.
        add_filter('corex_assistant_callback_listeners', static fn (): array => []);
    }

    add_filter('pre_option_corex_assistant_enabled', static fn (): string => '1');
    add_filter('pre_option_corex_assistant_groq_key', static fn (): string => 'e2e-key');

    global $wpdb;
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_corex_ai_') . '%',
        $wpdb->esc_like('_transient_timeout_corex_ai_') . '%',
        $wpdb->esc_like('corex_ai_lock_') . '%',
    ));

    add_filter('corex_assistant_knowledge_provider', static fn (): object => new class () implements \Corex\Assistant\Knowledge\KnowledgeProvider {
        public function for(string $locale): \Corex\Assistant\Knowledge\Knowledge
        {
            return new \Corex\Assistant\Knowledge\Knowledge($locale, 'CoreX E2E', ['We build websites.'], [
                ['label' => 'Our websites', 'url' => home_url('/assistant-e2e/')],
            ]);
        }
    });

    add_filter('pre_http_request', static function ($pre, array $args, string $url) {
        if (! str_contains($url, '/chat/completions')) {
            return $pre;
        }
        $body = json_decode((string) ($args['body'] ?? ''), true);
        $messages = is_array($body['messages'] ?? null) ? $body['messages'] : [];
        $question = strtolower((string) (end($messages)['content'] ?? ''));
        $answer = str_contains($question, 'price')
            ? "It depends on what you need. Our team can tell you more.\n\n[[TEAM]]"
            : 'We build websites. Read ' . home_url('/assistant-e2e/') . ' or https://elsewhere.example/ for more.';

        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers'  => [],
            'body'     => (string) wp_json_encode(['choices' => [['message' => ['content' => $answer]]]]),
            'cookies'  => [],
        ];
    }, 10, 3);
});
