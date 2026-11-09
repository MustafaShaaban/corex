<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Chain;

defined('ABSPATH') || exit;

/**
 * One model service the assistant can ask (spec 129): where it answers, which model, with which key.
 *
 * Every service in {@see CATALOGUE} speaks the OpenAI chat-completions format, so one client talks to
 * all of them. The order of the catalogue is the order of the chain: the free tier with the best
 * Arabic first, and each next one taking over while the one before it is spent. The labels are for
 * the Settings screen only: a visitor never sees which service answered.
 */
final class Provider
{
    /**
     * id => label, base address, default model, and the extra request fields that service needs.
     * `reasoning_effort: low` keeps a reasoning model from spending the answer's budget on thinking.
     *
     * @var array<string, array{label: string, base: string, model: string, extra: array<string, string>}>
     */
    public const CATALOGUE = [
        'gemini' => [
            'label' => 'Google Gemini',
            'base' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'model' => 'gemini-flash-latest',
            'extra' => ['reasoning_effort' => 'low'],
        ],
        'groq' => [
            'label' => 'Groq',
            'base' => 'https://api.groq.com/openai/v1',
            'model' => 'openai/gpt-oss-120b',
            'extra' => ['reasoning_effort' => 'low'],
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'base' => 'https://openrouter.ai/api/v1',
            'model' => 'openrouter/free',
            'extra' => [],
        ],
        'mistral' => [
            'label' => 'Mistral',
            'base' => 'https://api.mistral.ai/v1',
            'model' => 'mistral-small-latest',
            'extra' => [],
        ],
    ];

    /**
     * @param array<string, string> $extra
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $base,
        public readonly string $model,
        public readonly string $key,
        public readonly array $extra = [],
    ) {
    }

    public function endpoint(): string
    {
        return rtrim($this->base, '/') . '/chat/completions';
    }
}
