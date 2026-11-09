<?php

/**
 * What the CoreX Assistant answers and shows (spec 129): the service's reply and its "busy" answers,
 * the visitor's strings and the filter over them, the button's markup, the hand-off form, the Settings
 * panel and page, and secrets saved without autoload.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Corex\Assistant\Admin\AssistantPanel;
use Corex\Assistant\AssistantService;
use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Chain\ChatRouter;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\ModelClient;
use Corex\Assistant\Forms\AssistantCallbackForm;
use Corex\Assistant\Front\AssistantButton;
use Corex\Assistant\Front\AssistantStrings;
use Corex\Assistant\Knowledge\Knowledge;
use Corex\Assistant\Knowledge\KnowledgeProvider;
use Corex\Assistant\Limits\VisitorLimits;
use Corex\Config\Settings\SettingsPages;
use Corex\Config\Settings\SettingsRegistry;
use Corex\Config\Settings\SettingsStore;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Tests\Unit\Assistant\AssistantFixtures;

beforeEach(function () {
    Functions\stubTranslationFunctions();
    Functions\stubEscapeFunctions();
    Functions\when('wp_json_encode')->alias('json_encode');
});

/** The service, whose one keyed service answers `$answer`, or fails for null. */
function assistantService(?string $answer, array $values = ['assistant.groq.key' => 'k'], array &$sent = []): AssistantService
{
    $cooldowns = [];
    $client = new ModelClient(static function (string $url, array $args) use ($answer, &$sent): array {
        $sent = json_decode($args['body'], true);

        return $answer === null
            ? ['status' => 503, 'headers' => [], 'body' => '']
            : ['status' => 200, 'headers' => [], 'body' => (string) json_encode(['choices' => [['message' => ['content' => $answer]]]])];
    });
    $settings = AssistantFixtures::settings($values);
    $knowledge = new class implements KnowledgeProvider {
        public function for(string $locale): Knowledge
        {
            return new Knowledge($locale, 'Example Studio', ['We build websites.'], [['label' => 'Contact', 'url' => 'https://example.com/contact/']]);
        }
    };

    return new AssistantService(
        $settings,
        new ChatRouter($client, AssistantFixtures::cooldowns($cooldowns), static fn (): int => 0),
        $knowledge,
        new AssistantStrings($settings, static fn (): string => 'Example Studio'),
        'example.com',
    );
}

it('answers from the knowledge, with the instructions first and the visitor’s messages after', function () {
    $sent = [];
    $reply = assistantService('Write to us at https://example.com/contact/ [[TEAM]]', ['assistant.groq.key' => 'k'], $sent)
        ->answer([['role' => 'user', 'content' => 'How do I reach you?']], 'en');

    expect($reply)->toBe([
        'reply' => 'Write to us at',
        'team' => true,
        'links' => [['url' => 'https://example.com/contact/', 'label' => 'Contact']],
        'busy' => false,
    ])
        ->and($sent['messages'][0]['role'])->toBe('system')
        ->and($sent['messages'][0]['content'])->toContain('"Example Studio Assistant"')
        ->and($sent['messages'][1])->toBe(['role' => 'user', 'content' => 'How do I reach you?']);
});

it('answers in the visitor’s language, with the site’s voice for it, whatever the page’s', function () {
    Filters\expectApplied('corex_assistant_voice')->andReturnUsing(
        static fn (array $voice, string $language): array => $language === 'en' ? ['Keep it formal.', 7] : $voice,
    );
    $sent = [];

    assistantService('Hello', ['assistant.groq.key' => 'k'], $sent)->answer([['role' => 'user', 'content' => 'What do you do?']], 'ar');

    expect($sent['messages'][0]['content'])->toContain('- Keep it formal.')
        ->toEndWith('Reply ONLY in the language whose code is "en", whatever the language of the KNOWLEDGE or of earlier messages.');
});

it('answers busy, offering the team, when nothing can answer', function (?string $answer, array $values) {
    expect(assistantService($answer, $values)->answer([['role' => 'user', 'content' => 'Hi']], 'en'))
        ->toBe(AssistantService::busy());
})->with([
    'no service keyed' => ['Hi', []],
    'every service failed' => [null, ['assistant.groq.key' => 'k']],
    'nothing left after shaping' => ['https://elsewhere.test/', ['assistant.groq.key' => 'k']],
]);

it('names the assistant after the site, or as Settings name it', function () {
    $strings = new AssistantStrings(AssistantFixtures::settings([]), static fn (): string => 'Example Studio');
    $named = new AssistantStrings(AssistantFixtures::settings(['assistant.name' => 'Ava']), static fn (): string => 'Example Studio');

    expect($strings->for('en')['name'])->toBe('Example Studio Assistant')
        ->and($strings->for('en')['open'])->toBe('Chat with Example Studio Assistant')
        ->and($named->for('en')['welcome'])->toContain('I’m Ava.')
        ->and($strings->for('en')['done'])->toContain('%s');
});

it('lets a site replace its strings per language, but not add keys or change their shape', function () {
    Filters\expectApplied('corex_assistant_strings')->andReturnUsing(static function (array $strings, string $locale): array {
        return $locale === 'ar'
            ? ['name' => 'مساعد', 'suggestions' => ['سؤال', '', 7], 'note' => ['not', 'a string'], 'extra' => 'x', 'welcome' => '  '] + $strings
            : $strings;
    });

    $arabic = (new AssistantStrings(AssistantFixtures::settings([]), static fn (): string => 'Example'))->for('ar');

    expect($arabic['name'])->toBe('مساعد')
        ->and($arabic['suggestions'])->toBe(['سؤال', '7'])
        ->and($arabic['note'])->toBeString()->toContain('AI assistant')
        ->and($arabic['welcome'])->toContain('Example Assistant')
        ->and($arabic)->not->toHaveKey('extra');
});

it('names no model or its maker in anything a visitor reads', function () {
    $strings = (new AssistantStrings(AssistantFixtures::settings([]), static fn (): string => 'Example'))->for('en');

    expect((string) json_encode($strings))->not->toMatch('/gemini|groq|openrouter|mistral|openai|chatgpt|claude|google/i');
});

it('draws the button, the invitation and the empty panel, with every setting escaped into the markup', function () {
    // WordPress answers an empty address with an empty string; the stub would write "http://".
    Functions\when('esc_url')->returnArg();
    $html = AssistantButton::markup([
        'locale' => 'ar',
        'dir' => 'rtl',
        'endpoint' => 'https://example.com/wp-json/corex/v1/assistant',
        'form' => '',
        'nonce' => 'abc',
        'mark' => '',
        'country' => 'AE',
        'countriesFirst' => 'AE,SA',
        'siteZone' => 'Asia/Dubai',
        'strings' => ['name' => 'Helper "x"', 'open' => 'Open <chat>', 'teaserTitle' => 'Hi', 'teaserText' => 'Ask', 'dismiss' => 'Dismiss'],
    ]);

    expect($html)->toContain('dir="rtl"')
        ->toContain('data-locale="ar"')
        ->toContain('data-form=""')
        ->toContain('data-countries-first="AE,SA"')
        ->toContain('aria-controls="' . AssistantButton::PANEL_ID . '"')
        ->toContain('aria-expanded="false"')
        ->toContain('class="corex-assistant__mark"')
        ->not->toContain('<chat>')
        ->not->toContain('<img');
});

it('reads a WordPress locale as the language the chat is keyed by', function (string $locale, string $language) {
    expect(AssistantButton::language($locale))->toBe($language);
})->with([
    ['ar', 'ar'],
    ['en_US', 'en'],
    ['pt_BR', 'pt'],
    ['', 'en'],
]);

it('shows only on public pages when the assistant is live', function (bool $admin, array $values, bool $shows) {
    Functions\when('is_admin')->justReturn($admin);
    Functions\when('is_feed')->justReturn(false);
    Functions\when('is_embed')->justReturn(false);
    Functions\when('is_customize_preview')->justReturn(false);
    $settings = AssistantFixtures::settings($values);
    $button = new AssistantButton($settings, new AssistantStrings($settings, static fn (): string => 'x'), static fn (): string => '');

    expect($button->shows())->toBe($shows);
})->with([
    'public and live' => [false, ['assistant.enabled' => '1', 'assistant.groq.key' => 'k'], true],
    'admin' => [true, ['assistant.enabled' => '1', 'assistant.groq.key' => 'k'], false],
    'off' => [false, ['assistant.groq.key' => 'k'], false],
]);

it('asks the hand-off’s questions as a CoreX form, with the engine’s own listeners', function () {
    $form = new AssistantCallbackForm();
    $fields = $form->fields();

    expect($form->slug)->toBe('corex-assistant-callback')
        ->and(array_keys($fields))->toBe(['name', 'phone', 'email', 'channel', 'time', 'transcript', 'page'])
        ->and($fields['name']['rules'])->toContain('required')
        ->and($fields['phone']['rules'])->toBe(['required', 'phone'])
        ->and($fields['email']['rules'])->not->toContain('required')
        ->and(array_keys($fields['channel']['options']))->toBe(['call', 'whatsapp', 'email'])
        ->and($form->listeners())->toBe([StoreSubmissionListener::class, SendEmailListener::class]);
});

it('lets a site send its own email for a callback in place of CoreX’s, keeping storage', function () {
    Filters\expectApplied('corex_assistant_callback_listeners')->andReturnUsing(
        static fn (array $listeners): array => [StoreSubmissionListener::class, 'Acme\\BrandedCallbackMail', 42],
    );

    expect((new AssistantCallbackForm())->listeners())->toBe([StoreSubmissionListener::class, 'Acme\\BrandedCallbackMail']);
});

it('shows each service’s state and today’s count under the Settings section, and only that section', function () {
    $store = ['groq' => 2_000];
    $counts = [];
    $panel = new AssistantPanel(
        AssistantFixtures::settings(['assistant.groq.key' => 'k', 'assistant.mistral.key' => 'k']),
        new Cooldowns(static fn (): array => $store, static function (): void {
        }, static fn (): int => 1_000),
        new VisitorLimits(static function (string $key) use (&$counts): mixed {
            return $counts[$key] ?? 4;
        }, static function (): void {
        }, static fn (): int => 1_000, static fn (): int => 50),
        static fn (int $at): string => 'at ' . $at,
    );

    $html = $panel->extra('before', 'assistant');

    expect($html)->toStartWith('before')
        ->toContain('Resting until at 2000')
        ->toContain('No key: skipped')
        ->toContain('Ready')
        ->toContain('4 of 50')
        ->toContain('data-corex-assistant-check ')
        ->and($panel->extra('before', 'captcha'))->toBe('before');
});

it('declares the Assistant page and its fields on the Settings screen, keys write-only', function () {
    $registry = new SettingsRegistry();
    $fields = $registry->sections()['assistant']['fields'];
    $page = (new SettingsPages())->find('assistant');

    expect($page)->not->toBeNull()
        ->and($page->sections)->toBe(['assistant'])
        ->and($page->addon->pluginFile)->toBe('corex-assistant/corex-assistant.php')
        ->and($fields['assistant.enabled']['type'])->toBe('checkbox')
        ->and($fields['assistant.mark']['type'])->toBe('media')
        ->and($registry->secretKeys())->toContain('assistant.gemini.key', 'assistant.groq.key', 'assistant.openrouter.key', 'assistant.mistral.key')
        ->and($fields)->toHaveKeys(array_map(
            static fn (string $id): string => AssistantSettings::modelField($id),
            array_keys(\Corex\Assistant\Chain\Provider::CATALOGUE),
        ));
});

it('saves a secret with autoload off, and leaves every other setting’s autoload to WordPress', function () {
    $calls = [];
    Functions\when('update_option')->alias(static function (string $name, string $value, ?bool $autoload = null) use (&$calls): bool {
        $calls[] = [$name, $autoload];

        return true;
    });
    $store = new SettingsStore();

    $store->save('assistant.groq.key', 'k', true);
    $store->save('assistant.name', 'Ava');

    expect($calls)->toBe([['corex_assistant_groq_key', false], ['corex_assistant_name', null]]);
});
