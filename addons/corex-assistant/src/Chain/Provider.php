<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Chain;

defined('ABSPATH') || exit;

/**
 * One model service the assistant can ask (spec 129): where it answers, which model, with which key,
 * and how a request to it is written.
 *
 * The catalogue's order is the chain's default order: the free tiers first, each taking over while the
 * one before it is spent, and the paid services last, as the last resort. The owner can reorder them and
 * switch each on or off on the Assistant screen; a paid service stays off until the owner turns it on.
 * Most services speak the OpenAI chat-completions format (`api` = `chat`); Claude is asked through
 * Anthropic's own Messages API (`api` = `messages`). The labels are for the owner's screen only: a
 * visitor never sees which service answered.
 */
final class Provider
{
    public const API_CHAT = 'chat';
    public const API_MESSAGES = 'messages';

    /**
     * id => label, base address, default model, whether answers cost money, the request format, and the
     * request fields the service needs or refuses.
     *
     * - `extra`: fields added to every request (`reasoning_effort: low` keeps a reasoning model from
     *   spending the answer's room on thinking; Claude's `output_config.effort` does the same).
     * - `tokens`: the name of the answer-length field (GPT-5 models refuse `max_tokens`).
     * - `sampling`: whether `temperature` may be sent (GPT-5 models and this Claude version refuse it).
     *
     * @var array<string, array{label: string, base: string, model: string, paid: bool, api: string, extra: array<string, mixed>, tokens: string, sampling: bool}>
     */
    public const CATALOGUE = [
        'gemini' => [
            'label' => 'Google Gemini',
            'base' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            // Flash-Lite: Flash answered "high demand" (503) on 2026-11-09 while Lite answered in 1.5 s, on a live site.
            'model' => 'gemini-flash-lite-latest',
            'paid' => false,
            'api' => self::API_CHAT,
            'extra' => [],
            'tokens' => 'max_tokens',
            'sampling' => true,
        ],
        'groq' => [
            'label' => 'Groq',
            'base' => 'https://api.groq.com/openai/v1',
            'model' => 'openai/gpt-oss-120b',
            'paid' => false,
            'api' => self::API_CHAT,
            'extra' => ['reasoning_effort' => 'low'],
            'tokens' => 'max_tokens',
            'sampling' => true,
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'base' => 'https://openrouter.ai/api/v1',
            'model' => 'openrouter/free',
            'paid' => false,
            'api' => self::API_CHAT,
            'extra' => [],
            'tokens' => 'max_tokens',
            'sampling' => true,
        ],
        'mistral' => [
            'label' => 'Mistral',
            'base' => 'https://api.mistral.ai/v1',
            'model' => 'mistral-small-latest',
            'paid' => false,
            'api' => self::API_CHAT,
            'extra' => [],
            'tokens' => 'max_tokens',
            'sampling' => true,
        ],
        'openai' => [
            'label' => 'OpenAI',
            'base' => 'https://api.openai.com/v1',
            'model' => 'gpt-5-mini',
            'paid' => true,
            'api' => self::API_CHAT,
            'extra' => ['reasoning_effort' => 'low'],
            'tokens' => 'max_completion_tokens',
            'sampling' => false,
        ],
        'anthropic' => [
            'label' => 'Anthropic Claude',
            'base' => 'https://api.anthropic.com/v1',
            'model' => 'claude-haiku-5-5',
            'paid' => true,
            'api' => self::API_MESSAGES,
            'extra' => ['output_config' => ['effort' => 'low']],
            'tokens' => 'max_tokens',
            'sampling' => false,
        ],
    ];

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $base,
        public readonly string $model,
        public readonly string $key,
        public readonly array $extra = [],
        public readonly bool $paid = false,
        public readonly string $api = self::API_CHAT,
        public readonly string $tokensField = 'max_tokens',
        public readonly bool $sampling = true,
    ) {
    }

    /** A service of the catalogue, with its key and the model the owner chose ('' for the catalogue's). */
    public static function fromCatalogue(string $id, string $key, string $model = ''): self
    {
        $entry = self::CATALOGUE[$id];

        return new self(
            $id,
            $entry['label'],
            $entry['base'],
            $model !== '' ? $model : $entry['model'],
            $key,
            $entry['extra'],
            $entry['paid'],
            $entry['api'],
            $entry['tokens'],
            $entry['sampling'],
        );
    }

    public static function isPaid(string $id): bool
    {
        return (bool) (self::CATALOGUE[$id]['paid'] ?? false);
    }

    public function endpoint(): string
    {
        return rtrim($this->base, '/') . ($this->api === self::API_MESSAGES ? '/messages' : '/chat/completions');
    }
}
