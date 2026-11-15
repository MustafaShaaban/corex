<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Admin;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\ModelClient;
use Corex\Assistant\Limits\VisitorLimits;

/**
 * "Check services" (spec 129, User Story 3): each service with a key, on or off, is asked a visitor's
 * question with the full instructions and knowledge, so the owner sees whether it answers, and how fast,
 * at the size a real question has. A small test question passed while real ones were refused on a live
 * site (2026-11-09). Asking one that is off lets the owner try a key, a paid one too, before switching it on.
 * A service that answers is no longer resting; one that fails rests as it would for a
 * visitor. A question sent to a paid service counts against today's paid limit, as a visitor's does. The
 * answer names the service and its model, never its key.
 */
final class ServiceCheck
{
    /** Every service must answer inside shared hosting's 30-second request. */
    private const BUDGET_SECONDS = 26;

    private const MIN_SECONDS = 4;

    /**
     * @param \Closure(): list<array{role: string, content: string}> $sample a visitor's question, as sent
     * @param \Closure(): float                                      $clock  seconds
     */
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly ModelClient $client,
        private readonly Cooldowns $cooldowns,
        private readonly VisitorLimits $limits,
        private readonly \Closure $sample,
        private readonly \Closure $clock,
    ) {
    }

    /**
     * @return list<array{service: string, model: string, on: bool, answered: bool, seconds: float, detail: string}>
     */
    public function run(): array
    {
        $chain = $this->settings->keyed();
        if ($chain === []) {
            return [];
        }

        $timeout = max(self::MIN_SECONDS, intdiv(self::BUDGET_SECONDS, count($chain)));
        $messages = ($this->sample)();
        $results = [];
        foreach ($chain as $provider) {
            if ($provider->paid) {
                $this->limits->recordPaid();
            }
            $started = ($this->clock)();
            $answer = $this->client->ask($provider, $messages, $timeout);
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
}
