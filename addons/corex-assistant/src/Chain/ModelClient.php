<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Chain;

defined('ABSPATH') || exit;

/**
 * One question to one model service (spec 129, FR-004): in the OpenAI chat-completions format, or in
 * Anthropic's Messages API for Claude.
 *
 * The HTTP call is passed in, so the classification of every answer is tested without a network:
 * `wp_remote_post` in production ({@see fromWordPress()}).
 */
final class ModelClient
{
    /** Room for a short answer; a reasoning model's thinking counts against it too. */
    private const MAX_TOKENS = 900;

    /** The Messages API version Claude is asked in; it refuses `temperature`, `top_p` and `top_k`. */
    private const ANTHROPIC_VERSION = '2026-10-21';

    /** The words a service uses when the spent limit is its daily one. */
    private const DAILY_HINTS = ['per day', 'perday', 'per_day', 'daily', 'requests per day', 'rpd', 'free-models-per-day'];

    /**
     * @param \Closure(string, array<string, mixed>): array{status?: int, headers?: array<string, string>, body?: string, error?: string} $transport
     * @param string $siteUrl  named to OpenRouter, which asks callers to say who they are
     * @param string $siteName likewise
     */
    public function __construct(
        private readonly \Closure $transport,
        private readonly string $siteUrl = '',
        private readonly string $siteName = '',
    ) {
    }

    public static function fromWordPress(): self
    {
        return new self(
            static function (string $url, array $args): array {
                $response = wp_remote_post($url, $args);
                if (is_wp_error($response)) {
                    return ['error' => $response->get_error_message()];
                }

                $headers = [];
                foreach ((array) wp_remote_retrieve_headers($response) as $name => $value) {
                    $headers[strtolower((string) $name)] = is_array($value) ? (string) reset($value) : (string) $value;
                }

                return [
                    'status' => (int) wp_remote_retrieve_response_code($response),
                    'headers' => $headers,
                    'body' => (string) wp_remote_retrieve_body($response),
                ];
            },
            (string) home_url('/'),
            html_entity_decode((string) get_bloginfo('name'), ENT_QUOTES, 'UTF-8'),
        );
    }

    /**
     * @param list<array{role: string, content: string}> $messages the system message first
     */
    public function ask(Provider $provider, array $messages, int $timeout): string|Failure
    {
        $response = ($this->transport)($provider->endpoint(), [
            'timeout' => $timeout,
            'headers' => $this->headers($provider),
            // A visitor's broken UTF-8 is replaced, not allowed to empty the whole request.
            'body' => (string) json_encode($this->body($provider, $messages), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return $this->read($response, $provider->api);
    }

    /** @return array<string, string> */
    private function headers(Provider $provider): array
    {
        if ($provider->api === Provider::API_MESSAGES) {
            return [
                'x-api-key' => $provider->key,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'Content-Type' => 'application/json',
            ];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $provider->key,
            'Content-Type' => 'application/json',
        ];
        if ($provider->id === 'openrouter' && $this->siteUrl !== '') {
            // OpenRouter asks callers to name themselves; it does not change the answer.
            $headers['HTTP-Referer'] = $this->siteUrl;
            $headers['X-Title'] = $this->siteName !== '' ? $this->siteName : $this->siteUrl;
        }

        return $headers;
    }

    /**
     * @param list<array{role: string, content: string}> $messages the system message first
     * @return array<string, mixed>
     */
    private function body(Provider $provider, array $messages): array
    {
        $body = ['model' => $provider->model];
        if ($provider->api === Provider::API_MESSAGES) {
            // The Messages API takes the instructions apart from the conversation.
            $system = array_filter($messages, static fn (array $m): bool => $m['role'] === 'system');
            $body['system'] = implode("\n\n", array_column($system, 'content'));
            $body['messages'] = self::fromVisitor(array_values(array_filter($messages, static fn (array $m): bool => $m['role'] !== 'system')));
        } else {
            $body['messages'] = $messages;
        }
        $body[$provider->tokensField] = self::MAX_TOKENS;
        if ($provider->sampling) {
            $body['temperature'] = 0.3;
        }

        return array_merge($body, $provider->extra);
    }

    /**
     * The conversation from the visitor's first message on: the Messages API refuses one that opens with
     * the assistant, which a history cut to its last messages often does.
     *
     * @param list<array{role: string, content: string}> $messages
     * @return list<array{role: string, content: string}>
     */
    private static function fromVisitor(array $messages): array
    {
        while ($messages !== [] && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        return $messages;
    }

    /**
     * @param array{status?: int, headers?: array<string, string>, body?: string, error?: string} $response
     */
    public function read(array $response, string $api = Provider::API_CHAT): string|Failure
    {
        if (isset($response['error'])) {
            return new Failure(Failure::SERVER, 0, null, (string) $response['error']);
        }

        $status = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');

        if ($status === 429) {
            $retry = (string) ($response['headers']['retry-after'] ?? '');

            return new Failure(
                $this->mentionsDaily($body) ? Failure::DAILY : Failure::LIMIT,
                $status,
                ctype_digit($retry) ? (int) $retry : null,
                $this->excerpt($body),
            );
        }

        // Gemini answers a wrong key with a 400, and some services a model they do not have: that is the
        // site's setting, not the visitor's message, so the service rests.
        if (in_array($status, [400, 422], true) && $this->mentionsKeyOrModel($body)) {
            return new Failure(Failure::CONFIG, $status, null, $this->excerpt($body));
        }

        if (in_array($status, [400, 413, 422], true) || ($status === 403 && $this->mentionsRefusal($body))) {
            return new Failure(Failure::REJECTED, $status, null, $this->excerpt($body));
        }

        if (in_array($status, [401, 402, 403, 404], true)) {
            return new Failure(Failure::CONFIG, $status, null, $this->excerpt($body));
        }

        // Anthropic's 529 (overloaded) lands here too: a minute's rest, as for any server error.
        if ($status < 200 || $status >= 300) {
            return new Failure(Failure::SERVER, $status, null, $this->excerpt($body));
        }

        $data = json_decode($body, true);

        return is_array($data) && $api === Provider::API_MESSAGES ? self::messagesAnswer($data, $status) : self::chatAnswer($data, $status);
    }

    /**
     * A chat-completions answer: the first choice's text. No text for this message (its safety filter, or
     * a model that spent its room thinking) rests no service; an unreadable answer is the service's fault.
     */
    private static function chatAnswer(mixed $data, int $status): string|Failure
    {
        $choice = is_array($data) ? ($data['choices'][0] ?? null) : null;
        $text = is_array($choice) ? ($choice['message']['content'] ?? null) : null;
        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }

        $finish = is_array($choice) ? (string) ($choice['finish_reason'] ?? '') : '';
        $refused = in_array($finish, ['content_filter', 'length', 'safety'], true) || ! empty($choice['message']['refusal'] ?? null);

        return new Failure($refused ? Failure::REJECTED : Failure::SERVER, $status, null, $refused ? 'no answer: ' . $finish : 'empty answer');
    }

    /**
     * A Messages API answer: its text blocks joined (thinking blocks are skipped). A refusal, or room
     * spent before any text, rests no service.
     *
     * @param array<string, mixed> $data
     */
    private static function messagesAnswer(array $data, int $status): string|Failure
    {
        $stop = (string) ($data['stop_reason'] ?? '');
        if ($stop === 'refusal') {
            return new Failure(Failure::REJECTED, $status, null, 'no answer: refusal');
        }

        $parts = [];
        foreach ((array) ($data['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text' && is_string($block['text'] ?? null)) {
                $parts[] = $block['text'];
            }
        }
        $text = trim(implode("\n\n", $parts));
        if ($text !== '') {
            return $text;
        }

        return $stop === 'max_tokens'
            ? new Failure(Failure::REJECTED, $status, null, 'no answer: max_tokens')
            : new Failure(Failure::SERVER, $status, null, 'empty answer');
    }

    /** A 400 or 422 about the key or the model, which a retry from any visitor would get too. */
    private function mentionsKeyOrModel(string $body): bool
    {
        return preg_match('/api[ _-]?key|API_KEY_INVALID|model[^.]{0,40}(not found|does not exist|not exist|invalid|unknown|decommissioned)/i', $body) === 1;
    }

    /** A 403 about the message (moderation, safety), not about the key. */
    private function mentionsRefusal(string $body): bool
    {
        return preg_match('/moderat|flagged|safety|content[ _-]?policy|blocked/i', $body) === 1;
    }

    private function mentionsDaily(string $body): bool
    {
        $lower = strtolower($body);
        foreach (self::DAILY_HINTS as $hint) {
            if (str_contains($lower, $hint)) {
                return true;
            }
        }

        return false;
    }

    /** A short, single-line note for the Assistant screen. Never the key: it is not in the body. */
    private function excerpt(string $body): string
    {
        $data = json_decode($body, true);
        $message = is_array($data) ? ($data['error']['message'] ?? $data['message'] ?? '') : '';
        $text = is_string($message) && $message !== '' ? $message : $body;

        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $text)), 0, 200);
    }
}
