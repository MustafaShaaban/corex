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

/**
 * "Check services" (spec 129, User Story 3): one short question to each keyed service, so the owner
 * sees a new key work without a visitor having to find out. A service that answers is not resting any
 * more. The answer names the service and its model, never its key.
 */
final class ServiceCheck
{
    /** Four services must answer inside shared hosting's 30-second request. */
    private const SECONDS_EACH = 6;

    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly ModelClient $client,
        private readonly Cooldowns $cooldowns,
    ) {
    }

    /**
     * @return list<array{service: string, model: string, answered: bool, detail: string}>
     */
    public function run(): array
    {
        $results = [];
        foreach ($this->settings->chain() as $provider) {
            $answer = $this->client->ask($provider, [['role' => 'user', 'content' => 'Reply with the single word: ready']], self::SECONDS_EACH);
            if (is_string($answer)) {
                $this->cooldowns->clear($provider->id);
            }
            $results[] = [
                'service' => $provider->label,
                'model' => $provider->model,
                'answered' => is_string($answer),
                'detail' => is_string($answer) ? '' : trim($answer->kind . ' ' . ($answer->status > 0 ? $answer->status : '') . ' ' . $answer->detail),
            ];
        }

        return $results;
    }
}
