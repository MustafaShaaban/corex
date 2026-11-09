<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Chain;

defined('ABSPATH') || exit;

/**
 * One question to one model service, in the OpenAI chat-completions format (spec 129, FR-004).
 *
 * The HTTP call is passed in, so the classification of every answer is tested without a network:
 * `wp_remote_post` in production ({@see fromWordPress()}).
 */
final class ModelClient
{
    /** Room for a short answer; a reasoning model's thinking counts against it too. */
    private const MAX_TOKENS = 900;

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
        $headers = [
            'Authorization' => 'Bearer ' . $provider->key,
            'Content-Type' => 'application/json',
        ];
        if ($provider->id === 'openrouter' && $this->siteUrl !== '') {
            // OpenRouter asks callers to name themselves; it does not change the answer.
            $headers['HTTP-Referer'] = $this->siteUrl;
            $headers['X-Title'] = $this->siteName !== '' ? $this->siteName : $this->siteUrl;
        }

        $body = array_merge([
            'model' => $provider->model,
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => self::MAX_TOKENS,
        ], $provider->extra);

        $response = ($this->transport)($provider->endpoint(), [
            'timeout' => $timeout,
            'headers' => $headers,
            // A visitor's broken UTF-8 is replaced, not allowed to empty the whole request.
            'body' => (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return $this->read($response);
    }

    /**
     * @param array{status?: int, headers?: array<string, string>, body?: string, error?: string} $response
     */
    public function read(array $response): string|Failure
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

        if ($status < 200 || $status >= 300) {
            return new Failure(Failure::SERVER, $status, null, $this->excerpt($body));
        }

        $data = json_decode($body, true);
        $choice = is_array($data) ? ($data['choices'][0] ?? null) : null;
        $text = is_array($choice) ? ($choice['message']['content'] ?? null) : null;
        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }

        // No answer to this message (its safety filter, or a model that spent its room thinking): the
        // service is fine for the next visitor, so it does not rest. An unreadable answer is the service's.
        $finish = is_array($choice) ? (string) ($choice['finish_reason'] ?? '') : '';
        $refused = in_array($finish, ['content_filter', 'length', 'safety'], true) || ! empty($choice['message']['refusal'] ?? null);

        return new Failure($refused ? Failure::REJECTED : Failure::SERVER, $status, null, $refused ? 'no answer: ' . $finish : 'empty answer');
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

    /** A short, single-line note for the Settings screen. Never the key: it is not in the body. */
    private function excerpt(string $body): string
    {
        $data = json_decode($body, true);
        $message = is_array($data) ? ($data['error']['message'] ?? $data['message'] ?? '') : '';
        $text = is_string($message) && $message !== '' ? $message : $body;

        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $text)), 0, 200);
    }
}
