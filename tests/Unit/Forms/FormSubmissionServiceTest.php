<?php

/**
 * Unit tests for the submission orchestrator (spec US3: FR-008, FR-010, FR-011, SC-006).
 *
 * Honeypot and validation failures must short-circuit before any dispatch — proven
 * with a real dispatcher + a recording listener (no mocks of internal collaborators).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\SubmissionChallenge;
use Corex\Forms\Submission\SubmissionRefusal;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;
use Brain\Monkey\Functions;
use Corex\Security\ChallengeVerifier;
use Corex\Support\BootLogger;

final class ContactTestForm extends Form
{
    public string $slug = 'contact';

    /**
     * @var array<string,array{type?:string,rules?:list<string>,label?:string}>
     */
    protected array $fields = [
        'name'    => ['type' => 'text', 'rules' => ['required']],
        'email'   => ['type' => 'email', 'rules' => ['required', 'email']],
        'message' => ['type' => 'textarea', 'rules' => ['required']],
    ];
}

/**
 * @param list<FormSubmittedEvent> $dispatched captured events, by reference
 */
function submissionService(array &$dispatched, ?Form $form = null, ?ChallengeVerifier $provider = null): FormSubmissionService
{
    $registry = new FormRegistry();
    $registry->register($form ?? new ContactTestForm());

    $rules    = new RuleRegistry();
    $listeners = new ListenerProvider();
    $listeners->listen(FormSubmittedEvent::class, function (FormSubmittedEvent $event) use (&$dispatched): void {
        $dispatched[] = $event;
    });

    return new FormSubmissionService(
        $registry,
        new SchemaResolver($rules),
        new Validator($rules),
        new EventDispatcher($listeners, new BootLogger(debug: false)),
        challenge: new SubmissionChallenge($provider),
    );
}

/**
 * The rejection reasons go through `__()` (#148 item 2) — they used to be bare literals, so a
 * translated site got English at exactly the moment something had gone wrong.
 */
beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('rejects a filled honeypot and dispatches nothing', function () {
    $dispatched = [];

    $response = submissionService($dispatched)->handle('contact', [
        'name' => 'Mustafa', 'email' => 'm@example.com', 'message' => 'Hi', 'corex_hp' => 'i-am-a-bot',
    ]);

    expect($response->isOk())->toBeFalse()
        ->and($dispatched)->toBe([]);
});

it('rejects an invalid payload with field errors and dispatches nothing', function () {
    $dispatched = [];

    $response = submissionService($dispatched)->handle('contact', ['name' => '', 'email' => 'bad', 'message' => '']);

    expect($response->isOk())->toBeFalse()
        ->and($response->status)->toBe(422)
        ->and($response->value)->toMatchArray(['name' => 'required', 'email' => 'email', 'message' => 'required'])
        ->and($dispatched)->toBe([]);
});

it('dispatches one event carrying the validated values on a valid submission', function () {
    $dispatched = [];

    $response = submissionService($dispatched)->handle('contact', [
        'name' => 'Mustafa', 'email' => 'm@example.com', 'message' => 'Hello there',
    ]);

    expect($response->isOk())->toBeTrue()
        ->and($dispatched)->toHaveCount(1)
        ->and($dispatched[0])->toBeInstanceOf(FormSubmittedEvent::class)
        ->and($dispatched[0]->formSlug)->toBe('contact')
        ->and($dispatched[0]->values)->toBe(['name' => 'Mustafa', 'email' => 'm@example.com', 'message' => 'Hello there']);
});

it('rejects an unknown form slug non-fatally', function () {
    $dispatched = [];

    $response = submissionService($dispatched)->handle('does-not-exist', []);

    expect($response->isOk())->toBeFalse()
        ->and($response->status)->toBe(404)
        ->and($dispatched)->toBe([]);
});

// Spec 104, US2 (#264): a form defined in code could not ask for the site's challenge.

/** A lead form that says it is protected. */
function protectedTestForm(): Form
{
    return new class extends Form {
        public string $slug = 'callback';

        protected array $fields = ['phone' => ['type' => 'text', 'rules' => ['required']]];

        public function protection(): array
        {
            return ['captcha' => 'on'];
        }
    };
}

/** A provider that accepts one token. */
function providerAccepting(string $accepted): ChallengeVerifier
{
    return new class($accepted) implements ChallengeVerifier {
        public function __construct(private string $accepted)
        {
        }

        public function verify(string $token): bool
        {
            return $token === $this->accepted;
        }
    };
}

it('refuses a protected form whose token the provider does not accept, before anything else happens', function (array $sent) {
    $dispatched = [];
    $service    = submissionService($dispatched, protectedTestForm(), providerAccepting('good-token'));

    // The answers are invalid too: the challenge is what refuses it, and no field is judged.
    $response = $service->handle('callback', $sent + ['phone' => '']);

    expect($response->isOk())->toBeFalse()
        ->and($response->status)->toBe(422)
        ->and($response->value)->toBeInstanceOf(SubmissionRefusal::class)
        ->and($response->value->code)->toBe('challenge_failed')
        ->and($dispatched)->toBe([]);
})->with([
    'no token'         => [[]],
    'a rejected token' => [['captcha_token' => 'forged']],
]);

it('accepts a protected form whose token the provider accepts, and keeps the token out of the answers', function () {
    $dispatched = [];
    $service    = submissionService($dispatched, protectedTestForm(), providerAccepting('good-token'));

    $response = $service->handle('callback', ['phone' => '0100', 'captcha_token' => 'good-token']);

    expect($response->isOk())->toBeTrue()
        ->and($response->value)->toBe(['phone' => '0100'])
        ->and($dispatched[0]->values)->toBe(['phone' => '0100']);
});

it('does not challenge a form that did not ask to be protected', function () {
    $dispatched = [];
    $service    = submissionService($dispatched, new ContactTestForm(), providerAccepting('good-token'));

    $response = $service->handle('contact', ['name' => 'Mustafa', 'email' => 'm@example.com', 'message' => 'Hi']);

    expect($response->isOk())->toBeTrue()
        ->and($dispatched)->toHaveCount(1);
});

it('accepts a protected form on a site with no challenge provider', function () {
    $dispatched = [];
    $service    = submissionService($dispatched, protectedTestForm());

    expect($service->handle('callback', ['phone' => '0100'])->isOk())->toBeTrue();
});
