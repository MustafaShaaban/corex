<?php

/**
 * A requested time is asked for once (spec 129, slice 3, FR-021 to FR-023): the slots route and the
 * `assistant_slot` rule leave out claimed times, the callback's `prepare()` claims the time and writes the
 * team's line, its last listener names or releases the claim, and a request binned in the inbox frees its
 * time. The claims are an array here; the database's own claim and its race are tested on the real
 * options table (`tests/Integration/Assistant/AssistantSlotsTest.php`).
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Booking\OpenSlots;
use Corex\Assistant\Booking\SlotMoment;
use Corex\Assistant\Booking\StoredCallbackSlot;
use Corex\Assistant\Booking\SubmissionSlots;
use Corex\Assistant\Booking\TeamClock;
use Corex\Assistant\Forms\AssistantCallbackForm;
use Corex\Assistant\Forms\AssistantSlotRule;
use Corex\Assistant\Forms\SlotClaimListener;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Forms\FormRegistry;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Validation\AnswersRefused;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;
use Corex\Mail\EmailLanguage;
use Corex\Support\BootLogger;
use Corex\Tests\Unit\Assistant\AssistantFixtures;
use Corex\Tests\Unit\Assistant\MemorySlotClaims;

/** A moment on Cairo's clock, Monday 9 November 2026 unless another day is written. */
function assistantCairo(string $wallClock): int
{
    return (new DateTimeImmutable('2026-11-09 ' . $wallClock, new DateTimeZone('Africa/Cairo')))->getTimestamp();
}

/** Every day 9:00 to 12:00 in Cairo, hour-long slots, no notice, today only. */
function assistantSlotSettings(): AssistantSettings
{
    return AssistantFixtures::settings([], [AssistantSettings::BOOKING_OPTION => [
        'enabled' => true,
        'timezone' => 'Africa/Cairo',
        'days' => [0, 1, 2, 3, 4, 5, 6],
        'start' => '09:00',
        'end' => '12:00',
        'slot' => 60,
        'notice' => 0,
        'horizon' => 1,
    ]]);
}

/** The times open at 6:00 in Cairo. */
function assistantOpenSlots(MemorySlotClaims $claims, string $now = '06:00'): OpenSlots
{
    return new OpenSlots(assistantSlotSettings(), $claims, static fn (): int => assistantCairo($now));
}

/** The callback form, writing the team's line with a plain English clock. */
function assistantSlotCallbackForm(MemorySlotClaims $claims): AssistantCallbackForm
{
    $hours = assistantSlotSettings()->booking();
    $clock = static fn (int $at, string $zone): string => (new DateTimeImmutable('@' . $at))->setTimezone(new DateTimeZone($zone))->format('D j M, g:i a');

    return new AssistantCallbackForm(
        assistantOpenSlots($claims),
        static fn (int $at, string $zone): string => $hours->describe($at, $zone, $clock),
    );
}

beforeEach(function () {
    Functions\stubTranslationFunctions();
});

it('leaves the times other visitors asked for out of what it offers', function () {
    $slots = assistantOpenSlots(new MemorySlotClaims([assistantCairo('10:00') => 41]));

    expect($slots->open())->toBe([assistantCairo('09:00'), assistantCairo('11:00')])
        ->and($slots->isOpen(assistantCairo('10:00')))->toBeFalse()
        ->and($slots->zone())->toBe('Africa/Cairo');
});

it('claims a time once, and clears the claims of times gone by first', function () {
    $yesterday = assistantCairo('10:00') - 86_400;
    $claims = new MemorySlotClaims([$yesterday => 7]);
    $slots = assistantOpenSlots($claims);

    expect($slots->claim(assistantCairo('09:00')))->toBeTrue()
        ->and($slots->claim(assistantCairo('09:00')))->toBeFalse()
        ->and($claims->rows)->toBe([assistantCairo('09:00') => 0]);
});

it('reads a slot sent as UTC seconds or as a moment with its offset, and nothing else', function (mixed $sent, ?int $read) {
    expect(SlotMoment::read($sent))->toBe($read);
})->with([
    'seconds, as the chat sends them' => ['1794211200', 1_794_211_200],
    'seconds as a number' => [1_794_211_200, 1_794_211_200],
    'what toISOString() writes' => ['2026-11-09T08:00:00.000Z', 1_794_211_200],
    'an offset' => ['2026-11-09T10:00:00+02:00', 1_794_211_200],
    'empty' => ['', null],
    'zero' => ['0', null],
    'words' => ['tomorrow', null],
    'a date with no time' => ['2026-11-09', null],
    'too many digits' => ['1794211200000', null],
    'a list' => [['1794211200'], null],
]);

it('writes a slot as the inbox shows it, in UTC', function () {
    expect(SlotMoment::write(1_794_211_200))->toBe('2026-11-09T08:00:00Z');
});

it('refuses a time that is no longer on offer, and lets "any time" through', function (mixed $sent, ?string $error) {
    $rule = new AssistantSlotRule(assistantOpenSlots(new MemorySlotClaims([assistantCairo('10:00') => 41]), '09:30'));

    expect($rule->validate($sent, [], []))->toBe($error);
})->with([
    'an open time' => [(string) assistantCairo('11:00'), null],
    'any time' => ['', null],
    'left out' => [null, null],
    'asked for by another visitor' => [(string) assistantCairo('10:00'), 'slot_taken'],
    'already past' => [(string) assistantCairo('09:00'), 'slot_taken'],
    'outside the hours' => [(string) assistantCairo('13:00'), 'slot_taken'],
    'not a time' => ['soon', 'slot_taken'],
]);

it('claims the time and writes the team’s line itself, over what the browser wrote', function (array $sent, array $stored) {
    $claims = new MemorySlotClaims();

    expect(assistantSlotCallbackForm($claims)->prepare(['name' => 'Sara'] + $sent))->toEqual($stored + ['name' => 'Sara'] + $sent)
        ->and($claims->rows)->toBe([assistantCairo('10:00') => 0]);
})->with([
    'from another zone' => [
        ['slot' => (string) assistantCairo('10:00'), 'zone' => 'Asia/Riyadh', 'time' => 'Any time you like'],
        ['time' => 'Mon 9 Nov, 11:00 am Riyadh = Mon 9 Nov, 10:00 am Cairo', 'slot' => '2026-11-09T08:00:00Z', 'zone' => 'Asia/Riyadh'],
    ],
    'from the same zone' => [
        ['slot' => (string) assistantCairo('10:00'), 'zone' => 'Africa/Cairo'],
        ['time' => 'Mon 9 Nov, 10:00 am Cairo', 'slot' => '2026-11-09T08:00:00Z', 'zone' => 'Africa/Cairo'],
    ],
    'from a zone this site does not know' => [
        ['slot' => '2026-11-09T08:00:00.000Z', 'zone' => 'Mars/Olympus'],
        ['time' => 'Mon 9 Nov, 10:00 am Cairo', 'slot' => '2026-11-09T08:00:00Z', 'zone' => ''],
    ],
]);

it('keeps the time a visitor typed when they picked none, and claims nothing', function () {
    $claims = new MemorySlotClaims();
    $prepared = assistantSlotCallbackForm($claims)->prepare(['name' => 'Sara', 'time' => 'after work', 'zone' => 'Asia/Riyadh']);

    expect($prepared)->toEqual(['name' => 'Sara', 'time' => 'after work', 'slot' => '', 'zone' => 'Asia/Riyadh'])
        ->and($claims->rows)->toBe([]);
});

it('refuses the second of two visitors who were both offered a time and sent it together', function () {
    $claims = new MemorySlotClaims();
    $rule = new AssistantSlotRule(assistantOpenSlots($claims));
    $form = assistantSlotCallbackForm($claims);
    $sent = ['name' => 'Sara', 'slot' => (string) assistantCairo('10:00'), 'zone' => 'Africa/Cairo'];

    // Both pass the rule before either is prepared, as two requests in flight do.
    $bothPass = [$rule->validate($sent['slot'], [], $sent), $rule->validate($sent['slot'], [], $sent)];
    $form->prepare($sent);

    expect($bothPass)->toBe([null, null])
        ->and(static fn () => $form->prepare($sent))->toThrow(function (AnswersRefused $refused): void {
            expect($refused->errors)->toBe(['slot' => 'slot_taken']);
        })
        ->and($claims->rows)->toBe([assistantCairo('10:00') => 0]);
});

it('stores the first request for a time, refuses the next with "slot_taken", and keeps the claim named', function () {
    $claims = new MemorySlotClaims();
    $rules = new RuleRegistry();
    $rules->register(AssistantSlotRule::NAME, new AssistantSlotRule(assistantOpenSlots($claims)));
    $forms = new FormRegistry();
    $forms->register(assistantSlotCallbackForm($claims));
    $stored = [];
    $listeners = new ListenerProvider();
    $listeners->listen(FormSubmittedEvent::class, static function (FormSubmittedEvent $event) use ($claims, &$stored): void {
        $stored[] = $event->values;
        $event->storedAs(77);
        (new SlotClaimListener($claims))($event);
    });
    $service = new FormSubmissionService($forms, new SchemaResolver($rules), new Validator($rules), new EventDispatcher($listeners, new BootLogger(false)));
    $request = ['name' => 'Sara', 'channel' => 'call', 'phone' => '+20 100 123 4567', 'slot' => (string) assistantCairo('10:00'), 'zone' => 'Asia/Riyadh'];

    $first = $service->handle(AssistantCallbackForm::SLUG, $request);
    $second = $service->handle(AssistantCallbackForm::SLUG, ['name' => 'Omar'] + $request);

    expect($first->isOk())->toBeTrue()
        ->and($stored[0]['time'])->toBe('Mon 9 Nov, 11:00 am Riyadh = Mon 9 Nov, 10:00 am Cairo')
        ->and($second->status)->toBe(422)
        ->and($second->value)->toBe(['slot' => 'slot_taken'])
        ->and($stored)->toHaveCount(1)
        ->and($claims->rows)->toBe([assistantCairo('10:00') => 77]);
});

it('asks for the number or the address the chosen way of reaching them needs, through the engine’s required_if', function (array $sent, array $errors) {
    $rules = new RuleRegistry();
    $rules->register(AssistantSlotRule::NAME, new AssistantSlotRule(assistantOpenSlots(new MemorySlotClaims())));
    $schema = (new SchemaResolver($rules))->resolve(assistantSlotCallbackForm(new MemorySlotClaims())->fields());

    expect((new Validator($rules))->validate($schema, ['name' => 'Sara'] + $sent)->errors)->toBe($errors);
})->with([
    'a call with the number left out' => [['channel' => 'call'], ['phone' => 'required']],
    'WhatsApp with no number' => [['channel' => 'whatsapp', 'phone' => ''], ['phone' => 'required']],
    'an email with no address' => [['channel' => 'email', 'phone' => '+20 100 123 4567'], ['email' => 'required']],
    'an email with an address and no number' => [['channel' => 'email', 'email' => 'sara@example.com'], []],
]);

it('names the submission in its claim, and releases a claim nothing stored', function (?int $storedAs, array $rows) {
    $claims = new MemorySlotClaims([assistantCairo('10:00') => 0]);
    $event = new FormSubmittedEvent(AssistantCallbackForm::SLUG, ['slot' => SlotMoment::write(assistantCairo('10:00'))]);
    if ($storedAs !== null) {
        $event->storedAs($storedAs);
    }

    (new SlotClaimListener($claims))($event);

    expect($claims->rows)->toBe($rows);
})->with([
    'stored' => [77, [assistantCairo('10:00') => 77]],
    'not stored' => [null, []],
]);

it('leaves the claims alone for another form, or a request with no time', function (string $form, array $values) {
    $claims = new MemorySlotClaims([assistantCairo('10:00') => 0]);

    (new SlotClaimListener($claims))(new FormSubmittedEvent($form, $values));

    expect($claims->rows)->toBe([assistantCairo('10:00') => 0]);
})->with([
    'another form' => ['contact', ['slot' => SlotMoment::write(assistantCairo('10:00'))]],
    'no time' => [AssistantCallbackForm::SLUG, ['slot' => '']],
]);

it('settles the claim last, whatever a site puts in the callback’s listeners', function (array $filtered, array $listeners) {
    Filters\expectApplied('corex_assistant_callback_listeners')->andReturn($filtered);

    expect(assistantSlotCallbackForm(new MemorySlotClaims())->listeners())->toBe($listeners);
})->with([
    'the engine’s own' => [[StoreSubmissionListener::class, SendEmailListener::class], [StoreSubmissionListener::class, SendEmailListener::class, SlotClaimListener::class]],
    'the claim put first' => [[SlotClaimListener::class, StoreSubmissionListener::class], [StoreSubmissionListener::class, SlotClaimListener::class]],
    'nothing stored' => [['Acme\\CallbackMail'], ['Acme\\CallbackMail', SlotClaimListener::class]],
]);

it('frees a request’s time when it is binned or deleted, and takes it again on restore while it is free', function () {
    $claims = new MemorySlotClaims([assistantCairo('10:00') => 77]);
    $inbox = new SubmissionSlots($claims, static fn (int $id): ?int => $id === 77 ? assistantCairo('10:00') : null, static fn (): int => assistantCairo('06:00'));

    $inbox->statusChanged(77, SubmissionSlots::POST_TYPE, 'trash', 'private');
    $afterTrash = $claims->rows;
    $inbox->statusChanged(77, SubmissionSlots::POST_TYPE, 'private', 'trash');
    $afterRestore = $claims->rows;
    $inbox->deleted(77, SubmissionSlots::POST_TYPE);

    expect($afterTrash)->toBe([])
        ->and($afterRestore)->toBe([assistantCairo('10:00') => 77])
        ->and($claims->rows)->toBe([]);
});

it('restores a request whose time was taken since, or has passed, without claiming it', function (int $slot, array $rows) {
    $claims = new MemorySlotClaims($rows);
    $inbox = new SubmissionSlots($claims, static fn (int $id): ?int => $slot, static fn (): int => assistantCairo('10:30'));

    $inbox->statusChanged(77, SubmissionSlots::POST_TYPE, 'private', 'trash');

    expect($claims->rows)->toBe($rows);
})->with([
    'asked for by another visitor since' => [assistantCairo('11:00'), [assistantCairo('11:00') => 91]],
    'already past' => [assistantCairo('10:00'), []],
]);

it('pays no attention to posts that are not submissions', function () {
    $claims = new MemorySlotClaims([assistantCairo('10:00') => 77]);
    $inbox = new SubmissionSlots($claims, static fn (int $id): ?int => assistantCairo('10:00'), static fn (): int => assistantCairo('06:00'));

    $inbox->statusChanged(77, 'page', 'trash', 'publish');
    $inbox->deleted(77, 'post');

    expect($claims->rows)->toBe([assistantCairo('10:00') => 77]);
});

it('writes the team’s clock in the site’s language, with the site’s time format or a plain one', function (string $saved, string $line) {
    $language = new class implements EmailLanguage {
        public int $drawn = 0;

        public function during(callable $draw): mixed
        {
            $this->drawn++;

            return $draw();
        }
    };
    Functions\when('get_option')->justReturn($saved);
    Functions\when('wp_date')->alias(static fn (string $format, int $at, DateTimeZone $zone): string => (new DateTimeImmutable('@' . $at))->setTimezone($zone)->format($format));

    expect((new TeamClock($language))(assistantCairo('19:00'), 'Asia/Riyadh'))->toBe($line)
        ->and($language->drawn)->toBe(1);
})->with([
    'the site’s 24-hour clock' => ['H:i', 'Mon 9 Nov, 20:00'],
    'no format saved' => ['', 'Mon 9 Nov, 8:00 pm'],
]);

it('reads back the time a stored callback asked for, and nothing for another form', function (string $form, string $slot, ?int $read) {
    Functions\when('get_post_meta')->alias(static fn (int $id, string $key): string => match ($key) {
        'corex_form_slug' => $form,
        'corex_field_slot' => $slot,
        default => '',
    });

    expect((new StoredCallbackSlot())(77))->toBe($read);
})->with([
    'a callback with a time' => [AssistantCallbackForm::SLUG, '2026-11-09T08:00:00Z', 1_794_211_200],
    'a callback for any time' => [AssistantCallbackForm::SLUG, '', null],
    'another form' => ['contact', '2026-11-09T08:00:00Z', null],
]);
