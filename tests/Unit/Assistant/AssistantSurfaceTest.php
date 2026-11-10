<?php

/**
 * What the CoreX Assistant answers and shows (spec 129): the service's reply and its "busy" answers,
 * the visitor's strings and the filter over them, the button's markup, the hand-off form, and what a save
 * of CoreX → Assistant writes.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Corex\Assistant\Admin\AssistantScreenSave;
use Corex\Assistant\AssistantService;
use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Chain\ChatRouter;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\ModelClient;
use Corex\Assistant\Booking\OpenSlots;
use Corex\Assistant\Forms\AssistantCallbackForm;
use Corex\Assistant\Forms\SlotClaimListener;
use Corex\Assistant\Front\AssistantButton;
use Corex\Assistant\Front\AssistantStrings;
use Corex\Assistant\Knowledge\Knowledge;
use Corex\Assistant\Knowledge\KnowledgeProvider;
use Corex\Assistant\Limits\VisitorLimits;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Tests\Unit\Assistant\AssistantFixtures;
use Corex\Tests\Unit\Assistant\MemorySlotClaims;

beforeEach(function () {
    Functions\stubTranslationFunctions();
    Functions\stubEscapeFunctions();
    Functions\when('wp_json_encode')->alias('json_encode');
});

/** The service, whose one keyed service answers `$answer`, or fails for null. */
function assistantService(?string $answer, array $values = ['assistant.groq.key' => 'k'], array &$sent = []): AssistantService
{
    $cooldowns = [];
    $counts = [];
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
        new ChatRouter($client, AssistantFixtures::cooldowns($cooldowns), AssistantFixtures::limits($counts), static fn (): int => 0),
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

it('names the assistant after the site, or as the owner names it for a language', function () {
    $strings = new AssistantStrings(AssistantFixtures::settings([]), static fn (): string => 'Example Studio');
    $named = new AssistantStrings(AssistantFixtures::settings([], [AssistantSettings::BRAND_OPTION => [
        'copy' => ['en' => ['name' => 'Ava', 'suggestions' => ['Prices?']], 'ar' => ['welcome' => 'أهلًا']],
    ]]), static fn (): string => 'Example Studio');

    expect($strings->for('en')['name'])->toBe('Example Studio Assistant')
        ->and($strings->for('en')['open'])->toBe('Chat with Example Studio Assistant')
        ->and($strings->for('en')['done'])->toContain('%s')
        ->and($named->for('en')['name'])->toBe('Ava')
        ->and($named->for('en')['welcome'])->toContain('I’m Ava.')
        ->and($named->for('en')['suggestions'])->toBe(['Prices?'])
        ->and($named->for('ar')['welcome'])->toBe('أهلًا')
        ->and($named->for('ar')['name'])->toBe('Example Studio Assistant');
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
        'slots' => 'https://example.com/wp-json/corex/v1/assistant/slots',
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
        ->toContain('data-slots="https://example.com/wp-json/corex/v1/assistant/slots"')
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

/** The callback form with nothing on offer; its times are tested in AssistantSlotClaimsTest. */
function plainCallbackForm(): AssistantCallbackForm
{
    return new AssistantCallbackForm(
        new OpenSlots(AssistantFixtures::settings([]), new MemorySlotClaims(), static fn (): int => 0),
        static fn (int $at, string $zone): string => '',
    );
}

it('asks the hand-off’s questions as a CoreX form, with the engine’s own listeners', function () {
    $form = plainCallbackForm();
    $fields = $form->fields();

    expect($form->slug)->toBe('corex-assistant-callback')
        ->and(array_keys($fields))->toBe(['name', 'channel', 'phone', 'email', 'time', 'slot', 'zone', 'transcript', 'page'])
        ->and($fields['name']['rules'])->toContain('required')
        ->and($fields['phone']['rules'])->toBe(['required_if:channel,call,whatsapp', 'phone'])
        ->and($fields['email']['rules'])->toBe(['required_if:channel,email', 'email', 'max:160'])
        ->and($fields['slot']['rules'])->toBe(['max:40', 'assistant_slot'])
        ->and(array_keys($fields['channel']['options']))->toBe(['call', 'whatsapp', 'email'])
        ->and($form->listeners())->toBe([StoreSubmissionListener::class, SendEmailListener::class, SlotClaimListener::class]);
});

it('lets a site send its own email for a callback in place of CoreX’s, keeping storage', function () {
    Filters\expectApplied('corex_assistant_callback_listeners')->andReturnUsing(
        static fn (array $listeners): array => [StoreSubmissionListener::class, 'Acme\\BrandedCallbackMail', 42],
    );

    expect(plainCallbackForm()->listeners())->toBe([StoreSubmissionListener::class, 'Acme\\BrandedCallbackMail', SlotClaimListener::class]);
});

/**
 * A save over arrays: what was written (name => [value, autoload]) and what was removed.
 *
 * @param list<string> $locked Config keys `.env` sets
 */
function assistantScreenSave(array &$written, array &$removed, array $locked = []): AssistantScreenSave
{
    $store = [];

    return new AssistantScreenSave(
        AssistantFixtures::cooldowns($store),
        static function (string $option, mixed $value, bool $autoload) use (&$written): void {
            $written[$option] = [$value, $autoload];
        },
        static function (string $option) use (&$removed): void {
            $removed[] = $option;
        },
        static fn (string $key): bool => in_array($key, $locked, true),
        static fn (): array => ['en', 'ar'],
        'Africa/Cairo',
    );
}

it('saves the screen: the switch, keys and models where Config reads them, the rest unloaded, locked keys untouched', function () {
    Functions\when('sanitize_text_field')->alias(static fn ($text): string => trim(strip_tags((string) $text)));
    Functions\when('sanitize_textarea_field')->alias(static fn ($text): string => trim(strip_tags((string) $text)));
    Functions\when('esc_url_raw')->returnArg();
    $written = [];
    $removed = [];

    assistantScreenSave($written, $removed, ['assistant.mistral.key'])->save([
        'enabled' => '1',
        'services' => [
            'groq' => ['on' => '1', 'key' => " gsk_ab c\n", 'model' => ''],
            'mistral' => ['on' => '1', 'key' => 'ignored, set in .env', 'remove' => '1'],
            'gemini' => ['key' => '', 'remove' => '1'],
            'openai' => ['on' => '1', 'model' => 'gpt-5-nano'],
        ],
        'limits' => ['hour' => '10', 'paid' => 'x'],
        'brand' => ['icon' => 'https://example.com/i.png', 'copy' => ['ar' => ['name' => '<b>مساعد</b>', 'suggestions' => ['أ', '', 'ب']]]],
        'booking' => ['enabled' => '1', 'start' => '09:00', 'end' => '23:00', 'days' => ['5', '9']],
    ]);

    expect($written['corex_assistant_enabled'])->toBe(['1', true])
        ->and($written['corex_assistant_groq_key'])->toBe(['gsk_abc', true])
        ->and($written)->not->toHaveKey('corex_assistant_mistral_key')
        ->and($removed)->toContain('corex_assistant_gemini_key', 'corex_assistant_groq_model')
        ->and($removed)->not->toContain('corex_assistant_mistral_key')
        ->and($written['corex_assistant_openai_model'])->toBe(['gpt-5-nano', true])
        ->and($written[AssistantSettings::SERVICES_OPTION][0]['off'])->toBe(['gemini', 'openrouter', 'anthropic'])
        ->and($written[AssistantSettings::LIMITS_OPTION])->toBe([['hour' => 10, 'day' => 60, 'site' => 600, 'paid' => 50], false])
        ->and($written[AssistantSettings::BRAND_OPTION][0]['copy']['ar'])->toBe(['name' => 'مساعد', 'welcome' => '', 'suggestions' => ['أ', 'ب']])
        ->and($written[AssistantSettings::BOOKING_OPTION][0])->toMatchArray(['enabled' => true, 'timezone' => 'Africa/Cairo', 'days' => [5], 'end' => '23:00'])
        ->and(array_column(array_intersect_key($written, array_flip([
            AssistantSettings::SERVICES_OPTION,
            AssistantSettings::LIMITS_OPTION,
            AssistantSettings::BRAND_OPTION,
            AssistantSettings::BOOKING_OPTION,
        ])), 1))->toBe([false, false, false, false]);
});

it('leaves a switch the site’s .env sets as it is', function () {
    Functions\when('sanitize_text_field')->returnArg();
    Functions\when('sanitize_textarea_field')->returnArg();
    Functions\when('esc_url_raw')->returnArg();
    $written = [];
    $removed = [];

    assistantScreenSave($written, $removed, [AssistantSettings::ENABLED])->save(['enabled' => '1']);

    expect($written)->not->toHaveKey('corex_assistant_enabled');
});

it('saves the same values when what it saved is posted again', function () {
    Functions\when('sanitize_text_field')->alias(static fn ($text): string => trim((string) $text));
    Functions\when('sanitize_textarea_field')->alias(static fn ($text): string => trim((string) $text));
    Functions\when('esc_url_raw')->returnArg();
    $first = [];
    $second = [];
    $removed = [];
    $posted = ['booking' => ['enabled' => '1', 'timezone' => 'Asia/Riyadh', 'slot' => '45', 'notice' => '30', 'horizon' => '3', 'days' => ['0', '4']], 'limits' => ['site' => '100']];

    assistantScreenSave($first, $removed)->save($posted);
    assistantScreenSave($second, $removed)->save(['booking' => $first[AssistantSettings::BOOKING_OPTION][0], 'limits' => $first[AssistantSettings::LIMITS_OPTION][0]]);

    expect($second[AssistantSettings::BOOKING_OPTION])->toBe($first[AssistantSettings::BOOKING_OPTION])
        ->and($second[AssistantSettings::LIMITS_OPTION])->toBe($first[AssistantSettings::LIMITS_OPTION]);
});

it('moves a service up or down without scripts, and ignores a move off either end', function (string $move, array $expected) {
    expect(AssistantScreenSave::moved(['gemini', 'groq', 'openai'], $move))->toBe($expected);
})->with([
    'up' => ['groq:up', ['groq', 'gemini', 'openai']],
    'down' => ['groq:down', ['gemini', 'openai', 'groq']],
    'off the top' => ['gemini:up', ['gemini', 'groq', 'openai']],
    'unknown' => ['nobody:up', ['gemini', 'groq', 'openai']],
]);
