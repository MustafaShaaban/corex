<?php

/**
 * The model chain of the CoreX Assistant (spec 129, User Story 4): which services are asked, how a
 * request is sent, how each failure rests a service, and how the router moves on.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

use Corex\Assistant\Admin\ServiceCheck;
use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Chain\ChatRouter;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\Failure;
use Corex\Assistant\Chain\ModelClient;
use Corex\Assistant\Chain\Provider;
use Corex\Assistant\Limits\VisitorLimits;
use Corex\Tests\Unit\Assistant\AssistantFixtures;

/** Limits that never stop an answer, for tests about the chain alone. */
function assistantOpenLimits(): VisitorLimits
{
    $store = [];

    return AssistantFixtures::limits($store);
}

it('chains only the keyed services, in the catalogue order, with a model setting winning over the default', function () {
    $settings = AssistantFixtures::settings([
        'assistant.enabled' => '1',
        'assistant.mistral.key' => 'm-key',
        'assistant.gemini.key' => 'g-key',
        'assistant.groq.key' => '   ',
        'assistant.gemini.model' => 'gemini-x',
    ]);

    $chain = $settings->chain();

    expect(array_map(static fn (Provider $p): string => $p->id, $chain))->toBe(['gemini', 'mistral'])
        ->and($chain[0]->key)->toBe('g-key')
        ->and($chain[0]->model)->toBe('gemini-x')
        ->and($chain[1]->model)->toBe(Provider::CATALOGUE['mistral']['model'])
        ->and($settings->live())->toBeTrue();
});

it('is live only when switched on and keyed', function (array $values, bool $live) {
    expect(AssistantFixtures::settings($values)->live())->toBe($live);
})->with([
    'off by default, even keyed' => [['assistant.groq.key' => 'k'], false],
    'on with no key' => [['assistant.enabled' => '1'], false],
    'on and keyed' => [['assistant.enabled' => '1', 'assistant.groq.key' => 'k'], true],
]);

it('keeps a paid service off until the owner turns it on, whatever its key', function () {
    $keys = ['assistant.enabled' => '1', 'assistant.openai.key' => 'o', 'assistant.anthropic.key' => 'a'];

    $saved = [AssistantSettings::SERVICES_OPTION => ['order' => array_keys(Provider::CATALOGUE), 'off' => ['openai']]];

    expect(AssistantFixtures::settings($keys)->chain())->toBe([])
        ->and(AssistantFixtures::settings($keys)->live())->toBeFalse()
        ->and(array_map(static fn (Provider $p): string => $p->id, AssistantFixtures::settings($keys, $saved)->chain()))
        ->toBe(['anthropic']);
});

it('lists every keyed service for "Check services", on or off, while the chain keeps only those on', function () {
    $settings = AssistantFixtures::settings(['assistant.enabled' => '1', 'assistant.groq.key' => 'q', 'assistant.anthropic.key' => 'a']);
    $ids = static fn (array $providers): array => array_map(static fn (Provider $p): string => $p->id, $providers);

    expect($ids($settings->keyed()))->toBe(['groq', 'anthropic'])
        ->and($ids($settings->chain()))->toBe(['groq']);
});

it('keeps a paid service off when a release adds it after the owner saved the services', function () {
    $keys = ['assistant.enabled' => '1', 'assistant.groq.key' => 'q', 'assistant.anthropic.key' => 'a'];
    $savedBefore = [AssistantSettings::SERVICES_OPTION => ['order' => ['gemini', 'mistral'], 'off' => []]];

    expect(array_map(static fn (Provider $p): string => $p->id, AssistantFixtures::settings($keys, $savedBefore)->chain()))
        ->toBe(['groq']);
});

it('asks the services in the owner’s order, skipping the ones switched off, and adds new ones last', function () {
    $settings = AssistantFixtures::settings(
        ['assistant.gemini.key' => 'g', 'assistant.groq.key' => 'q', 'assistant.mistral.key' => 'm', 'assistant.anthropic.key' => 'a'],
        [AssistantSettings::SERVICES_OPTION => ['order' => ['anthropic', 'mistral', 'unknown', 'gemini'], 'off' => ['gemini']]],
    );

    expect(array_map(static fn (Provider $p): string => $p->id, $settings->chain()))->toBe(['anthropic', 'mistral', 'groq'])
        ->and($settings->order())->toBe(['anthropic', 'mistral', 'gemini', 'groq', 'openrouter', 'openai']);
});

it('reads the owner’s limits, keeping the defaults for anything missing or not a number', function () {
    $limits = AssistantFixtures::settings([], [AssistantSettings::LIMITS_OPTION => ['hour' => '5', 'site' => 'lots', 'paid' => -3]])->limits();

    expect($limits)->toBe(['hour' => 5, 'day' => 60, 'site' => 600, 'paid' => 0]);
});

it('reads the owner’s words per language and the button image', function () {
    $settings = AssistantFixtures::settings([], [AssistantSettings::BRAND_OPTION => [
        'icon' => ' https://example.com/mark.png ',
        'copy' => ['ar' => ['name' => ' مساعد ', 'welcome' => '', 'suggestions' => ['أ', '', 'ب', 'ج', 'د']]],
    ]]);

    expect($settings->brandCopy('ar'))->toBe(['name' => 'مساعد', 'suggestions' => ['أ', 'ب', 'ج']])
        ->and($settings->brandCopy('en'))->toBe([])
        ->and($settings->icon())->toBe('https://example.com/mark.png');
});

it('sends one chat-completions request with the key, the model and the service’s extra fields', function () {
    $sent = [];
    $client = new ModelClient(static function (string $url, array $args) use (&$sent): array {
        $sent = ['url' => $url, 'args' => $args];

        return AssistantFixtures::answer('  Hello  ');
    }, 'https://example.com/', 'Example');

    $answer = $client->ask(AssistantFixtures::provider('groq'), [['role' => 'user', 'content' => 'Hi']], 9);
    $body = json_decode($sent['args']['body'], true);

    expect($answer)->toBe('Hello')
        ->and($sent['url'])->toBe('https://api.groq.com/openai/v1/chat/completions')
        ->and($sent['args']['timeout'])->toBe(9)
        ->and($sent['args']['headers']['Authorization'])->toBe('Bearer key-groq')
        ->and($sent['args']['headers'])->not->toHaveKey('HTTP-Referer')
        ->and($body['model'])->toBe('openai/gpt-oss-120b')
        ->and($body['reasoning_effort'])->toBe('low')
        ->and($body['messages'])->toBe([['role' => 'user', 'content' => 'Hi']]);
});

it('names the site, not CoreX or a model, to OpenRouter only', function () {
    $headers = [];
    $client = new ModelClient(static function (string $url, array $args) use (&$headers): array {
        $headers = $args['headers'];

        return AssistantFixtures::answer('ok');
    }, 'https://example.com/', 'Example Studio');

    $client->ask(AssistantFixtures::provider('openrouter'), [['role' => 'user', 'content' => 'Hi']], 5);

    expect($headers['HTTP-Referer'])->toBe('https://example.com/')
        ->and($headers['X-Title'])->toBe('Example Studio');
});

it('sorts every way a service can fail by how long to leave it alone', function (array $response, string $kind, int $wait) {
    $failure = (new ModelClient(static fn (): array => []))->read($response);

    expect($failure)->toBeInstanceOf(Failure::class)
        ->and($failure->kind)->toBe($kind)
        ->and($failure->waitSeconds(86400 * 10 + 3600))->toBe($wait);
})->with([
    'minute limit, asked wait' => [['status' => 429, 'headers' => ['retry-after' => '17'], 'body' => '{"error":{"message":"Rate limit reached for requests per minute"}}'], Failure::LIMIT, 17],
    'limit, no wait given' => [['status' => 429, 'headers' => [], 'body' => '{}'], Failure::LIMIT, 60],
    'daily quota, until UTC midnight' => [['status' => 429, 'headers' => [], 'body' => '{"error":{"message":"Quota exceeded for metric: requests per day"}}'], Failure::DAILY, 86400 - 3600],
    'bad key' => [['status' => 401, 'headers' => [], 'body' => '{"error":{"message":"Invalid API key"}}'], Failure::CONFIG, 3600],
    'unknown model' => [['status' => 404, 'headers' => [], 'body' => '{}'], Failure::CONFIG, 3600],
    'no credit' => [['status' => 402, 'headers' => [], 'body' => '{}'], Failure::CONFIG, 3600],
    'a wrong key told as a 400' => [['status' => 400, 'headers' => [], 'body' => '{"error":{"message":"API key not valid. Please pass a valid API key.","status":"INVALID_ARGUMENT","details":[{"reason":"API_KEY_INVALID"}]}}'], Failure::CONFIG, 3600],
    'a retired model told as a 400' => [['status' => 400, 'headers' => [], 'body' => '{"error":{"message":"The model `x-1` has been decommissioned"}}'], Failure::CONFIG, 3600],
    'unprocessable message' => [['status' => 422, 'headers' => [], 'body' => '{"error":{"message":"Input too long"}}'], Failure::REJECTED, 0],
    'answer withheld by its safety filter' => [['status' => 200, 'headers' => [], 'body' => '{"choices":[{"message":{"content":null},"finish_reason":"content_filter"}]}'], Failure::REJECTED, 0],
    'room spent thinking' => [['status' => 200, 'headers' => [], 'body' => '{"choices":[{"message":{"content":""},"finish_reason":"length"}]}'], Failure::REJECTED, 0],
    'unreadable answer' => [['status' => 200, 'headers' => [], 'body' => 'not json'], Failure::SERVER, 60],
    'key refused' => [['status' => 403, 'headers' => [], 'body' => '{"error":{"message":"Permission denied"}}'], Failure::CONFIG, 3600],
    'message flagged by moderation' => [['status' => 403, 'headers' => [], 'body' => '{"error":{"message":"Input was flagged by moderation"}}'], Failure::REJECTED, 0],
    'malformed request' => [['status' => 400, 'headers' => [], 'body' => '{"error":{"message":"Invalid value"}}'], Failure::REJECTED, 0],
    'too large' => [['status' => 413, 'headers' => [], 'body' => ''], Failure::REJECTED, 0],
    'server error' => [['status' => 503, 'headers' => [], 'body' => 'down'], Failure::SERVER, 60],
    'no connection' => [['error' => 'cURL error 28'], Failure::SERVER, 60],
    'empty answer' => [AssistantFixtures::answer('   '), Failure::SERVER, 60],
]);

it('keeps a failure note short and on one line', function () {
    $failure = (new ModelClient(static fn (): array => []))->read(['status' => 500, 'body' => str_repeat("word \n", 100)]);

    expect($failure->detail)->not->toContain("\n")
        ->and(mb_strlen($failure->detail))->toBeLessThanOrEqual(200);
});

it('rests a failed service until its time, and forgets rests that are over', function () {
    $store = ['mistral' => 999_000];
    $cooldowns = AssistantFixtures::cooldowns($store);

    $cooldowns->cool('groq', new Failure(Failure::LIMIT, 429, 30));

    expect($store['groq']['until'])->toBe(1_000_030)
        ->and($cooldowns->isCooling('groq'))->toBeTrue()
        ->and($cooldowns->until('mistral'))->toBe(0);

    $cooldowns->clear('groq');
    expect($store)->toBe([]);
});

it('rests no service for a refused request, and keeps why it failed for the owner', function () {
    $store = [];
    $cooldowns = AssistantFixtures::cooldowns($store);
    $cooldowns->cool('groq', new Failure(Failure::REJECTED, 400, null, 'Invalid value'));

    expect($cooldowns->isCooling('groq'))->toBeFalse()
        ->and($cooldowns->lastFailure('groq'))->toBe(['kind' => Failure::REJECTED, 'status' => 400, 'detail' => 'Invalid value', 'at' => 1_000_000]);
});

it('asks the next service when one fails, skipping the resting ones and resting the one that failed', function () {
    $store = ['gemini' => 2_000_000];
    $asked = [];
    $client = new ModelClient(static function (string $url) use (&$asked): array {
        $asked[] = parse_url($url, PHP_URL_HOST);

        return str_contains($url, 'groq') ? ['status' => 429, 'headers' => [], 'body' => '{}'] : AssistantFixtures::answer('From the third');
    });
    $router = new ChatRouter($client, AssistantFixtures::cooldowns($store), assistantOpenLimits(), static fn (): int => 1_000_000);

    $answer = $router->answer([AssistantFixtures::provider('gemini'), AssistantFixtures::provider('groq'), AssistantFixtures::provider('openrouter')], [['role' => 'user', 'content' => 'Hi']]);

    expect($answer)->toBe('From the third')
        ->and($asked)->toBe(['api.groq.com', 'openrouter.ai'])
        ->and($store['groq']['until'])->toBe(1_000_060);
});

it('answers nothing when every service fails, and stops once its time budget is spent', function () {
    $store = [];
    $clock = 0;
    $calls = 0;
    $client = new ModelClient(static function () use (&$clock, &$calls): array {
        $calls++;
        $clock += 10;

        return ['status' => 503, 'headers' => [], 'body' => ''];
    });
    $router = new ChatRouter($client, AssistantFixtures::cooldowns($store), assistantOpenLimits(), static function () use (&$clock): int {
        return $clock;
    });

    $answer = $router->answer(array_map([AssistantFixtures::class, 'provider'], array_keys(Provider::CATALOGUE)), [['role' => 'user', 'content' => 'Hi']]);

    expect($answer)->toBeNull()
        ->and($calls)->toBe(2);
});

it('asks the next service after a refused message, resting none, and ends a finished rest when a service answers', function () {
    $store = ['openrouter' => 999_000];
    $client = new ModelClient(static fn (string $url): array => str_contains($url, 'groq')
        ? ['status' => 400, 'headers' => [], 'body' => '{"error":{"message":"Invalid value"}}']
        : AssistantFixtures::answer('Answered'));
    $router = new ChatRouter($client, AssistantFixtures::cooldowns($store), assistantOpenLimits(), static fn (): int => 0);

    $answer = $router->answer([AssistantFixtures::provider('groq'), AssistantFixtures::provider('openrouter')], [['role' => 'user', 'content' => 'Hi']]);

    expect($answer)->toBe('Answered')
        ->and($store)->not->toHaveKey('openrouter')
        ->and($store['groq']['until'])->toBe(0);
});

it('gives a call no more time than is left of the budget', function () {
    $store = [];
    $clock = 0;
    $timeouts = [];
    $client = new ModelClient(static function (string $url, array $args) use (&$clock, &$timeouts): array {
        $timeouts[] = $args['timeout'];
        $clock += 8;

        return ['status' => 503, 'headers' => [], 'body' => ''];
    });
    $router = new ChatRouter($client, AssistantFixtures::cooldowns($store), assistantOpenLimits(), static function () use (&$clock): int {
        return $clock;
    });

    $router->answer(array_map([AssistantFixtures::class, 'provider'], array_keys(Provider::CATALOGUE)), [['role' => 'user', 'content' => 'Hi']]);

    expect($timeouts)->toBe([10, 10, 6]);
});

it('sends a visitor’s broken text as text, not as an empty request', function () {
    $sent = '';
    $client = new ModelClient(static function (string $url, array $args) use (&$sent): array {
        $sent = $args['body'];

        return AssistantFixtures::answer('ok');
    });

    // A lone 0xB1 byte is not UTF-8: json_encode() would give up on the whole body without the substitute flag.
    $client->ask(AssistantFixtures::provider('groq'), [['role' => 'user', 'content' => "Hi \xB1 there"]], 5);

    expect(json_decode($sent, true)['messages'][0]['content'])->toBe("Hi \u{FFFD} there");
});

it('asks Claude through the Messages API: its own headers, the instructions apart, no temperature', function () {
    $sent = [];
    $client = new ModelClient(static function (string $url, array $args) use (&$sent): array {
        $sent = ['url' => $url, 'args' => $args];

        return AssistantFixtures::claudeAnswer('Hello from the site');
    });

    $answer = $client->ask(AssistantFixtures::provider('anthropic'), [['role' => 'system', 'content' => 'Rules'], ['role' => 'user', 'content' => 'Hi']], 8);
    $body = json_decode($sent['args']['body'], true);

    expect($answer)->toBe('Hello from the site')
        ->and($sent['url'])->toBe('https://api.anthropic.com/v1/messages')
        ->and($sent['args']['headers'])->toMatchArray(['x-api-key' => 'key-anthropic', 'anthropic-version' => '2026-10-21'])
        ->and($sent['args']['headers'])->not->toHaveKey('Authorization')
        ->and($body['system'])->toBe('Rules')
        ->and($body['messages'])->toBe([['role' => 'user', 'content' => 'Hi']])
        ->and($body['model'])->toBe('claude-haiku-5-5')
        ->and($body['output_config'])->toBe(['effort' => 'low'])
        ->and($body)->toHaveKey('max_tokens')
        ->and($body)->not->toHaveKeys(['temperature', 'top_p', 'top_k']);
});

it('sorts Claude’s answers: a refusal or spent room rests nothing, an overload rests a minute', function (array $response, string $kind) {
    expect((new ModelClient(static fn (): array => []))->read($response, Provider::API_MESSAGES)->kind)->toBe($kind);
})->with([
    'refused' => [AssistantFixtures::claudeAnswer('', 'refusal'), Failure::REJECTED],
    'room spent before any text' => [['status' => 200, 'headers' => [], 'body' => '{"content":[{"type":"thinking","thinking":"..."}],"stop_reason":"max_tokens"}'], Failure::REJECTED],
    'overloaded' => [['status' => 529, 'headers' => [], 'body' => '{"type":"error","error":{"type":"overloaded_error"}}'], Failure::SERVER],
    'limit' => [['status' => 429, 'headers' => ['retry-after' => '20'], 'body' => '{}'], Failure::LIMIT],
]);

it('asks OpenAI with the answer-length field its models take, and no temperature', function () {
    $body = [];
    $client = new ModelClient(static function (string $url, array $args) use (&$body): array {
        $body = json_decode($args['body'], true);

        return AssistantFixtures::answer('ok');
    });

    $client->ask(AssistantFixtures::provider('openai'), [['role' => 'user', 'content' => 'Hi']], 5);

    expect($body)->toHaveKey('max_completion_tokens')
        ->and($body)->not->toHaveKeys(['max_tokens', 'temperature'])
        ->and($body['reasoning_effort'])->toBe('low');
});

it('asks a paid service only while today’s paid requests last, counting each one sent, answered or not', function () {
    $store = [];
    $counts = [];
    $asked = [];
    $client = new ModelClient(static function (string $url) use (&$asked): array {
        $asked[] = parse_url($url, PHP_URL_HOST);

        return str_contains($url, 'openai') ? ['status' => 503, 'headers' => [], 'body' => ''] : AssistantFixtures::claudeAnswer('Paid answer');
    });
    $limits = AssistantFixtures::limits($counts, ['paid' => 2]);
    $router = new ChatRouter($client, AssistantFixtures::cooldowns($store), $limits, static fn (): int => 0);
    $chain = [AssistantFixtures::provider('openai'), AssistantFixtures::provider('anthropic')];

    $first = $router->answer($chain, [['role' => 'user', 'content' => 'Hi']]);
    $store = [];
    $second = $router->answer($chain, [['role' => 'user', 'content' => 'Again']]);

    expect($first)->toBe('Paid answer')
        ->and($second)->toBeNull()
        ->and($limits->paidToday())->toBe(2)
        ->and($asked)->toBe(['api.openai.com', 'api.anthropic.com']);
});

it('counts a question "Check services" sends to a paid service, even one that fails', function () {
    $store = [];
    $counts = [];
    $limits = AssistantFixtures::limits($counts);
    $settings = AssistantFixtures::settings(['assistant.openai.key' => 'o']);
    $check = new ServiceCheck(
        $settings,
        new ModelClient(static fn (): array => ['status' => 503, 'headers' => [], 'body' => '']),
        AssistantFixtures::cooldowns($store),
        $limits,
        static fn (): array => [['role' => 'user', 'content' => 'Hi']],
        static fn (): float => 0.0,
    );

    $results = $check->run();

    expect($results)->toHaveCount(1)
        ->and($results[0])->toMatchArray(['on' => false, 'answered' => false])
        ->and($limits->paidToday())->toBe(1);
});

it('counts nothing against the paid limit when a free service answers', function () {
    $store = [];
    $counts = [];
    $client = new ModelClient(static fn (): array => AssistantFixtures::answer('Free answer'));
    $limits = AssistantFixtures::limits($counts);
    $router = new ChatRouter($client, AssistantFixtures::cooldowns($store), $limits, static fn (): int => 0);

    $router->answer([AssistantFixtures::provider('groq'), AssistantFixtures::provider('openai')], [['role' => 'user', 'content' => 'Hi']]);

    expect($limits->paidToday())->toBe(0);
});

it('sends Claude the conversation from the visitor’s first message, as its API requires', function () {
    $body = [];
    $client = new ModelClient(static function (string $url, array $args) use (&$body): array {
        $body = json_decode($args['body'], true);

        return AssistantFixtures::claudeAnswer('ok');
    });

    $client->ask(AssistantFixtures::provider('anthropic'), [
        ['role' => 'system', 'content' => 'Rules'],
        ['role' => 'assistant', 'content' => 'An answer the history kept'],
        ['role' => 'user', 'content' => 'Hi'],
        ['role' => 'assistant', 'content' => 'Hello'],
        ['role' => 'user', 'content' => 'Prices?'],
    ], 5);

    expect(array_column($body['messages'], 'role'))->toBe(['user', 'assistant', 'user'])
        ->and($body['system'])->toBe('Rules');
});
