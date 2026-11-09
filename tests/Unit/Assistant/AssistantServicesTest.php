<?php

/**
 * The CoreX Assistant's services after slice 2 (spec 129, User Stories 7 and 8): the paid services
 * and how each is asked, the paid cap, the owner's order and a switch per service, and the defaults
 * the Settings screen draws before the first save.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Chain\Failure;
use Corex\Assistant\Chain\ModelClient;
use Corex\Assistant\Chain\Provider;
use Corex\Config\Settings\SettingsFieldValues;
use Corex\Tests\Unit\Assistant\AssistantFixtures;

/** @return list<string> the ids of a chain */
function chainIds(array $chain): array
{
    return array_map(static fn (Provider $provider): string => $provider->id, $chain);
}

it('asks Claude through the Messages API: its key header, its version, the instructions apart, no temperature', function () {
    $sent = [];
    $client = new ModelClient(static function (string $url, array $args) use (&$sent): array {
        $sent = ['url' => $url, 'args' => $args];

        return AssistantFixtures::messagesAnswer('Hello');
    });

    $answer = $client->ask(AssistantFixtures::provider('anthropic'), [
        ['role' => 'system', 'content' => 'Answer from the site.'],
        ['role' => 'user', 'content' => 'Hi'],
        ['role' => 'assistant', 'content' => 'Hello!'],
        ['role' => 'user', 'content' => 'Prices?'],
    ], 9);
    $body = json_decode($sent['args']['body'], true);

    expect($answer)->toBe('Hello')
        ->and($sent['url'])->toBe('https://api.anthropic.com/v1/messages')
        ->and($sent['args']['headers'])->toBe(['x-api-key' => 'key-anthropic', 'anthropic-version' => '2026-10-21', 'Content-Type' => 'application/json'])
        ->and($sent['args']['timeout'])->toBe(9)
        ->and($body['model'])->toBe('claude-haiku-5-5')
        ->and($body['system'])->toBe('Answer from the site.')
        ->and(array_column($body['messages'], 'role'))->toBe(['user', 'assistant', 'user'])
        ->and($body['max_tokens'])->toBe(900)
        ->and($body['output_config'])->toBe(['effort' => 'low'])
        ->and($body)->not->toHaveKey('temperature');
});

it('asks OpenAI with the answer-length field GPT-5 takes and no temperature', function () {
    $body = [];
    $client = new ModelClient(static function (string $url, array $args) use (&$body): array {
        $body = json_decode($args['body'], true);

        return AssistantFixtures::answer('ok');
    });

    $client->ask(AssistantFixtures::provider('openai'), [['role' => 'user', 'content' => 'Hi']], 5);

    expect($body['max_completion_tokens'])->toBe(900)
        ->and($body)->not->toHaveKeys(['max_tokens', 'temperature'])
        ->and($body['reasoning_effort'])->toBe('low');
});

it('reads a Messages answer by its text blocks, and sorts its refusals as the chat’s other refusals', function (array $response, string|null $text, ?string $kind) {
    $result = (new ModelClient(static fn (): array => []))->read($response, Provider::API_MESSAGES);

    expect(is_string($result) ? $result : null)->toBe($text)
        ->and($result instanceof Failure ? $result->kind : null)->toBe($kind);
})->with([
    'text after thinking' => [AssistantFixtures::messagesAnswer('  Hello there  '), 'Hello there', null],
    'declined by its safeguards' => [AssistantFixtures::messagesAnswer('', 'refusal'), null, Failure::REJECTED],
    'declined after some text' => [AssistantFixtures::messagesAnswer('I can', 'refusal'), null, Failure::REJECTED],
    'room spent thinking' => [AssistantFixtures::messagesAnswer('', 'max_tokens'), null, Failure::REJECTED],
    'no text and no reason' => [AssistantFixtures::messagesAnswer(''), null, Failure::SERVER],
    'overloaded' => [['status' => 529, 'headers' => [], 'body' => '{"type":"error","error":{"type":"overloaded_error","message":"Overloaded"}}'], null, Failure::SERVER],
    'its own rate limit' => [['status' => 429, 'headers' => ['retry-after' => '20'], 'body' => '{"type":"error","error":{"type":"rate_limit_error","message":"Rate limited"}}'], null, Failure::LIMIT],
    'a wrong key' => [['status' => 401, 'headers' => [], 'body' => '{"type":"error","error":{"type":"authentication_error","message":"invalid x-api-key"}}'], null, Failure::CONFIG],
]);

it('starts the paid services off, so a paid key alone never spends money', function () {
    $settings = AssistantFixtures::settings(['assistant.enabled' => '1', 'assistant.openai.key' => 'o', 'assistant.anthropic.key' => 'a']);

    expect($settings->chain())->toBe([])
        ->and($settings->live())->toBeFalse()
        ->and(chainIds($settings->keyed()))->toBe(['openai', 'anthropic']);
});

it('asks a paid service once it is switched on, after the free ones', function () {
    $settings = AssistantFixtures::settings([
        'assistant.anthropic.key' => 'a',
        'assistant.anthropic.on' => '1',
        'assistant.groq.key' => 'g',
    ]);

    expect(chainIds($settings->chain()))->toBe(['groq', 'anthropic'])
        ->and($settings->chain()[1]->paid)->toBeTrue();
});

it('asks the services in the owner’s order, dropping unknown ids and appending the ones the order left out', function () {
    $settings = AssistantFixtures::settings([
        'assistant.order' => 'mistral, nobody ,groq',
        'assistant.gemini.key' => 'k',
        'assistant.groq.key' => 'k',
        'assistant.mistral.key' => 'k',
    ]);

    expect($settings->order())->toBe(['mistral', 'groq', 'gemini', 'openrouter', 'openai', 'anthropic'])
        ->and(chainIds($settings->chain()))->toBe(['mistral', 'groq', 'gemini']);
});

it('skips a free service the owner switched off, whatever its key', function () {
    $settings = AssistantFixtures::settings(['assistant.enabled' => '1', 'assistant.groq.key' => 'k', 'assistant.groq.on' => '0']);

    expect($settings->chain())->toBe([])
        ->and($settings->live())->toBeFalse()
        ->and($settings->isOn('gemini'))->toBeTrue();
});

it('skips the paid services once today’s paid answers are spent, and a free one still answers', function () {
    $cooldowns = [];
    $counts = [];
    $paid = AssistantFixtures::paid($counts, 1);
    $asked = [];
    $client = new ModelClient(static function (string $url) use (&$asked): array {
        $asked[] = parse_url($url, PHP_URL_HOST);

        return str_contains($url, 'groq') ? ['status' => 429, 'headers' => [], 'body' => '{}'] : AssistantFixtures::answer('ok');
    });
    $chain = [AssistantFixtures::provider('openai'), AssistantFixtures::provider('gemini')];

    $first = AssistantFixtures::router($client, $cooldowns, static fn (): int => 0, $paid)->answer($chain, [['role' => 'user', 'content' => 'Hi']]);
    $second = AssistantFixtures::router($client, $cooldowns, static fn (): int => 0, $paid)->answer($chain, [['role' => 'user', 'content' => 'Hi']]);

    expect([$first, $second])->toBe(['ok', 'ok'])
        ->and($asked)->toBe(['api.openai.com', 'generativelanguage.googleapis.com'])
        ->and($paid->today())->toBe(1)
        ->and($paid->left())->toBeFalse();
});

it('never asks a paid service when the paid cap is 0', function () {
    $cooldowns = [];
    $counts = [];
    $asked = 0;
    $client = new ModelClient(static function () use (&$asked): array {
        $asked++;

        return AssistantFixtures::answer('ok');
    });

    $answer = AssistantFixtures::router($client, $cooldowns, static fn (): int => 0, AssistantFixtures::paid($counts, 0))
        ->answer([AssistantFixtures::provider('anthropic')], [['role' => 'user', 'content' => 'Hi']]);

    expect($answer)->toBeNull()->and($asked)->toBe(0);
});

it('counts a paid answer only, not a free one', function () {
    $cooldowns = [];
    $counts = [];
    $paid = AssistantFixtures::paid($counts);

    AssistantFixtures::router(new ModelClient(static fn (): array => AssistantFixtures::answer('ok')), $cooldowns, static fn (): int => 0, $paid)
        ->answer([AssistantFixtures::provider('groq')], [['role' => 'user', 'content' => 'Hi']]);

    expect($paid->today())->toBe(0);
});

it('reads the limits and the paid cap the owner set, and the defaults for anything that is not a whole number', function () {
    $set = AssistantFixtures::settings(['assistant.limit.hour' => '5', 'assistant.limit.day' => '9', 'assistant.daily_cap' => '100', 'assistant.paid_cap' => '0']);
    $odd = AssistantFixtures::settings(['assistant.limit.hour' => 'lots', 'assistant.paid_cap' => '-1']);

    expect($set->limits())->toBe(['hour' => 5, 'day' => 9, 'site' => 100])
        ->and($set->paidCap())->toBe(0)
        ->and($odd->limits())->toBe(['hour' => 20, 'day' => 60, 'site' => 600])
        ->and($odd->paidCap())->toBe(50);
});

it('draws on the Settings screen the defaults the assistant runs on', function () {
    foreach (AssistantSettings::DEFAULTS as $key => $default) {
        expect(SettingsFieldValues::DEFAULTS[$key] ?? null)->toBe($default, $key);
    }
    foreach (Provider::CATALOGUE as $id => $entry) {
        expect(SettingsFieldValues::DEFAULTS[AssistantSettings::switchField($id)] ?? null)->toBe($entry['paid'] ? '0' : '1', $id);
    }
});
