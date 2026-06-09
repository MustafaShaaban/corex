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
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;
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
function submissionService(array &$dispatched): FormSubmissionService
{
    $registry = new FormRegistry();
    $registry->register(new ContactTestForm());

    $rules    = new RuleRegistry();
    $provider = new ListenerProvider();
    $provider->listen(FormSubmittedEvent::class, function (FormSubmittedEvent $event) use (&$dispatched): void {
        $dispatched[] = $event;
    });

    return new FormSubmissionService(
        $registry,
        new SchemaResolver($rules),
        new Validator($rules),
        new EventDispatcher($provider, new BootLogger(debug: false)),
    );
}

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
