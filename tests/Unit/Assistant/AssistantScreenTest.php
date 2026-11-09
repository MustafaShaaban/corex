<?php

/**
 * What the owner sets on Settings → Assistant reaches visitors and the owner (spec 129, User Story
 * 11, FR-025, FR-026): the per-language words over the code's and CoreX's, a "Check services" of
 * real size, and the line under Booking.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Corex\Assistant\Admin\AssistantPanel;
use Corex\Assistant\Admin\ServiceCheck;
use Corex\Assistant\AssistantService;
use Corex\Assistant\Booking\SlotsController;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\ModelClient;
use Corex\Assistant\Front\AssistantStrings;
use Corex\Assistant\Knowledge\Knowledge;
use Corex\Assistant\Knowledge\KnowledgeProvider;
use Corex\Assistant\Limits\PaidAnswers;
use Corex\Assistant\Limits\VisitorLimits;
use Corex\Tests\Unit\Assistant\AssistantFixtures;

beforeEach(function () {
    foreach (['__', 'esc_html', 'esc_attr', 'esc_url', 'esc_html__', 'esc_attr__'] as $function) {
        Functions\when($function)->returnArg();
    }
    Functions\when('_n')->alias(static fn (string $one, string $many, int $count): string => $count === 1 ? $one : $many);
});

/** @param array<string, mixed> $values */
function screenStrings(array $values): AssistantStrings
{
    return new AssistantStrings(AssistantFixtures::settings($values), static fn (): string => 'Example Studio');
}

it('puts what the owner wrote for a language over what the site’s code says, and that over CoreX’s words', function () {
    Filters\expectApplied('corex_assistant_strings')->andReturnUsing(static fn (array $strings, string $locale): array => $locale === 'ar'
        ? ['name' => 'مساعد الكود', 'welcome' => 'أهلاً من الكود', 'busy' => 'مشغول'] + $strings
        : $strings);

    $arabic = screenStrings([
        'assistant.name' => 'Ava',
        'assistant.brand.ar.name' => 'مساعد الشاشة',
        'assistant.brand.ar.suggestion1' => 'ما خدماتكم؟',
    ])->for('ar');

    expect($arabic['name'])->toBe('مساعد الشاشة')
        ->and($arabic['welcome'])->toBe('أهلاً من الكود')
        ->and($arabic['busy'])->toBe('مشغول')
        ->and($arabic['suggestions'])->toBe(['ما خدماتكم؟']);
});

it('names the assistant after the language’s own name in every string that names it', function () {
    $english = screenStrings(['assistant.name' => 'Ava', 'assistant.brand.en.name' => 'Nora'])->for('en');

    expect($english['name'])->toBe('Nora')
        ->and($english['open'])->toContain('Nora')
        ->and($english['welcome'])->toContain('Nora');
});

it('keeps CoreX’s words for a language the owner wrote nothing for', function () {
    $french = screenStrings(['assistant.brand.ar.welcome' => 'أهلاً'])->for('fr');

    expect($french['welcome'])->toContain('Example Studio Assistant')
        ->and($french['suggestions'])->toHaveCount(3);
});

/**
 * The check over the given settings, each service answering as `$answers` says (a string, or a status
 * code), and a clock that moves 1.5 seconds a call.
 *
 * @param array<string, mixed>      $values
 * @param array<string, string|int> $answers host fragment => answer
 * @param array<string, mixed>      $store   the cooldowns, read back
 * @param list<array>               $calls   what was sent, read back
 */
function serviceCheck(array $values, array $answers, array &$store, array &$calls): ServiceCheck
{
    $settings = AssistantFixtures::settings($values);
    $client = new ModelClient(static function (string $url, array $args) use ($answers, &$calls): array {
        $calls[] = ['url' => $url, 'timeout' => $args['timeout'], 'body' => json_decode($args['body'], true)];
        foreach ($answers as $host => $answer) {
            if (str_contains($url, $host)) {
                return is_int($answer) ? ['status' => $answer, 'headers' => [], 'body' => '{"error":{"message":"Too many tokens"}}'] : AssistantFixtures::answer($answer);
            }
        }

        return ['status' => 500, 'headers' => [], 'body' => ''];
    });
    $strings = new AssistantStrings($settings, static fn (): string => 'Example Studio');
    $counts = [];
    $knowledge = new class () implements KnowledgeProvider {
        public function for(string $locale): Knowledge
        {
            return new Knowledge($locale, 'Example Studio', ['We build websites.'], []);
        }
    };
    $clock = 0.0;

    return new ServiceCheck(
        $settings,
        $client,
        AssistantFixtures::cooldowns($store),
        new AssistantService($settings, AssistantFixtures::router($client, $store, static fn (): int => 0), $knowledge, $strings, 'example.com'),
        $strings,
        static fn (): string => 'en',
        static function () use (&$clock): float {
            return $clock += 0.75;
        },
    );
}

it('asks every keyed service, on or off, a visitor’s question with the real instructions, and records how each did', function () {
    $store = [];
    $calls = [];
    $results = serviceCheck(
        ['assistant.groq.key' => 'g', 'assistant.mistral.key' => 'm', 'assistant.mistral.on' => '0', 'assistant.openai.key' => 'o'],
        ['groq' => 'We build websites.', 'mistral' => 413, 'openai' => 'Hello'],
        $store,
        $calls,
    )->run();

    expect(array_column($results, 'service'))->toBe(['Groq', 'Mistral', 'OpenAI'])
        ->and(array_column($results, 'on'))->toBe([true, false, false])
        ->and(array_column($results, 'answered'))->toBe([true, false, true])
        ->and($results[0]['seconds'])->toBe(0.8)
        ->and($results[1]['detail'])->toBe('rejected 413 Too many tokens')
        ->and($calls[0]['body']['messages'][0]['role'])->toBe('system')
        ->and($calls[0]['body']['messages'][0]['content'])->toContain('We build websites.')
        ->and($calls[0]['body']['messages'][1])->toBe(['role' => 'user', 'content' => 'What do you offer?'])
        ->and(array_keys($store))->toBe(['mistral']);
});

it('shares one 26-second budget among the services, giving each at least four', function (int $keyed, int $timeout) {
    $ids = array_slice(['gemini', 'groq', 'openrouter', 'mistral', 'openai', 'anthropic'], 0, $keyed);
    $values = [];
    foreach ($ids as $id) {
        $values['assistant.' . $id . '.key'] = 'k';
    }
    $store = [];
    $calls = [];

    serviceCheck($values, [], $store, $calls)->run();

    expect(array_unique(array_column($calls, 'timeout')))->toBe([$timeout]);
})->with([
    'one service' => [1, 26],
    'four services' => [4, 6],
    'six services' => [6, 4],
]);

it('says nothing and asks nobody with no key saved', function () {
    $store = [];
    $calls = [];

    expect(serviceCheck([], [], $store, $calls)->run())->toBe([])->and($calls)->toBe([]);
});

/**
 * The panel over settings, on Monday 10 Nov 2025 at 08:00 UTC, writing a moment as "Mon 09:00".
 *
 * @param array<string, mixed> $values
 */
function screenPanel(array $values): AssistantPanel
{
    $settings = AssistantFixtures::settings($values + ['assistant.enabled' => '1', 'assistant.groq.key' => 'k']);
    $nothing = static function (): void {
    };
    $now = static fn (): int => (new DateTimeImmutable('2025-11-10 08:00', new DateTimeZone('UTC')))->getTimestamp();

    return new AssistantPanel(
        $settings,
        new Cooldowns(static fn (): array => [], $nothing, $now),
        new VisitorLimits(static fn (): mixed => 0, $nothing, $now, static fn (): array => $settings->limits()),
        new PaidAnswers(static fn (): mixed => 0, $nothing, $now, static fn (): int => $settings->paidCap()),
        new SlotsController($settings, static fn (): string => 'UTC', $now),
        static fn (int $at): string => gmdate('D H:i', $at),
    );
}

it('says under Booking when the next time offered is, or that none is', function (array $values, string $expected) {
    expect(screenPanel($values)->extra('', 'assistant_booking'))->toContain($expected);
})->with([
    'times offered' => [
        ['assistant.booking.enabled' => '1', 'assistant.booking.days' => 'every', 'assistant.booking.start' => '09:00', 'assistant.booking.end' => '11:00', 'assistant.booking.slot' => '60', 'assistant.booking.notice' => '0', 'assistant.booking.horizon' => '1'],
        'The next time offered is Mon 09:00; 2 times in all.',
    ],
    'none left today' => [
        ['assistant.booking.enabled' => '1', 'assistant.booking.days' => 'every', 'assistant.booking.start' => '06:00', 'assistant.booking.end' => '07:00', 'assistant.booking.notice' => '0', 'assistant.booking.horizon' => '1'],
        'no time is offered now',
    ],
]);

it('says nothing under Booking while booking is off', function () {
    expect(screenPanel([])->extra('before', 'assistant_booking'))->toBe('before');
});

it('gives each of the site’s languages its own words on the screen, as the languages filter lists them', function () {
    Filters\expectApplied('corex_assistant_languages')->andReturn(['en', 'AR', 'not a language', 'pt_BR']);

    $fields = (new \Corex\Config\Settings\SettingsRegistry())->sections()['assistant']['fields'];

    expect($fields)->toHaveKeys([
        'assistant.brand.en.name', 'assistant.brand.ar.welcome', 'assistant.brand.ar.suggestion3', 'assistant.brand.pt.name',
    ])
        ->and(array_filter(array_keys($fields), static fn (string $key): bool => str_contains($key, 'not')))->toBe([])
        ->and($fields['assistant.brand.ar.name']['guide_label'])->toBe('Name in each language');
});
