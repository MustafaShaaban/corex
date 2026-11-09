<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Admin;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantService;
use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\ModelClient;
use Corex\Assistant\Front\AssistantStrings;

/**
 * "Check services" (spec 129, User Stories 3 and 11, FR-026): every keyed service, on or off, is
 * asked what a visitor would ask, with the real instructions and the site's knowledge. A service
 * that fails only on a request of real size (too many tokens for its free tier, too slow to think)
 * fails here too, and says why; its failure is recorded as the chat would record it, and a service
 * that answers is not resting any more. The answer names the service and its model, never its key.
 */
final class ServiceCheck
{
    /** Shared hosting ends a request at 30 seconds; the checks share what is left of it. */
    private const BUDGET_SECONDS = 26;

    /** Below this, a service could not answer a question of real size. */
    private const MIN_SECONDS = 4;

    /**
     * @param \Closure(): string $language the site's language, which the question is asked in
     * @param \Closure(): float  $clock    seconds
     */
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly ModelClient $client,
        private readonly Cooldowns $cooldowns,
        private readonly AssistantService $assistant,
        private readonly AssistantStrings $strings,
        private readonly \Closure $language,
        private readonly \Closure $clock,
    ) {
    }

    /**
     * @return list<array{service: string, model: string, on: bool, answered: bool, seconds: float, detail: string}>
     */
    public function run(): array
    {
        $services = $this->settings->keyed();
        if ($services === []) {
            return [];
        }

        $language = ($this->language)();
        $conversation = $this->assistant->conversation([['role' => 'user', 'content' => $this->question($language)]], $language);
        $timeout = max(self::MIN_SECONDS, intdiv(self::BUDGET_SECONDS, count($services)));

        $results = [];
        foreach ($services as $provider) {
            $started = ($this->clock)();
            $answer = $this->client->ask($provider, $conversation, $timeout);
            $seconds = round(($this->clock)() - $started, 1);
            if (is_string($answer)) {
                $this->cooldowns->clear($provider->id);
            } else {
                $this->cooldowns->cool($provider->id, $answer);
            }
            $results[] = [
                'service' => $provider->label,
                'model' => $provider->model,
                'on' => $this->settings->isOn($provider->id),
                'answered' => is_string($answer),
                'seconds' => $seconds,
                'detail' => is_string($answer) ? '' : trim($answer->kind . ' ' . ($answer->status > 0 ? $answer->status : '') . ' ' . $answer->detail),
            ];
        }

        return $results;
    }

    /** The first question the chat suggests to a visitor in that language. */
    private function question(string $language): string
    {
        $suggestions = $this->strings->for($language)['suggestions'] ?? [];

        return is_array($suggestions) && is_string($suggestions[0] ?? null) ? $suggestions[0] : 'What do you offer?';
    }
}
