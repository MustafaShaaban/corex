<?php

/**
 * Unit tests for the notification that says a site's own email design could not be used (spec
 * 106: FR-017).
 *
 * The email went, in the standard design. What failed is the site's template, and the people who
 * manage email are the ones who can ask its developer to mend it.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\CorexAbility;
use Corex\Config\Notifications\Producers\EmailDesignFailureNotificationProducer;
use Corex\Email\Design\EmailDesignFailedEvent;
use Corex\Events\ListenerProvider;
use Corex\Notifications\NotificationCategory;
use Corex\Tests\Support\AdminUrlStubs;
use Corex\Tests\Support\RecordingNotificationService;

beforeEach(function () {
    Functions\stubTranslationFunctions();
    AdminUrlStubs::install();
});

function designFailureNotes(EmailDesignFailedEvent ...$events): array
{
    $service = new RecordingNotificationService();
    $listeners = new ListenerProvider();
    (new EmailDesignFailureNotificationProducer($service, $listeners))->register();
    foreach ($events as $event) {
        foreach ($listeners->listenersFor($event) as $listener) {
            $listener($event);
        }
    }

    return $service->published;
}

it('tells the email managers which design failed and that the email still went', function () {
    $notes = designFailureNotes(new EmailDesignFailedEvent('AcmeSite\Mail\AcmeEmailDesign', EmailDesignFailedEvent::WITHOUT_MESSAGE));
    $note = $notes[0];

    expect($notes)->toHaveCount(1)
        ->and($note->type)->toBe('email.design_failed')
        ->and($note->category)->toBe(NotificationCategory::EMAIL)
        ->and($note->rendered['body'])->toContain('did not hold the message')
        ->and($note->rendered['body'])->toContain('CoreX used its standard design for the email instead')
        ->and($note->rendered['body'])->toContain('AcmeSite\Mail\AcmeEmailDesign')
        ->and($note->recipient->canBeSeenBy(9, fn (string $ability): bool => $ability === CorexAbility::MANAGE_EMAIL))->toBeTrue()
        ->and($note->recipient->canBeSeenBy(9, fn (string $ability): bool => false))->toBeFalse();
});

it('says what the design raised when it stopped with an error, and does not trail off when it said nothing', function () {
    $said = designFailureNotes(new EmailDesignFailedEvent('Acme', EmailDesignFailedEvent::THREW, 'RuntimeException: Undefined variable $logo'))[0];
    $silent = designFailureNotes(new EmailDesignFailedEvent('Acme', EmailDesignFailedEvent::THREW))[0];

    expect($said->rendered['body'])->toStartWith('It stopped with an error: RuntimeException: Undefined variable $logo CoreX used its standard design')
        ->and($silent->rendered['body'])->toStartWith('It stopped with an error. CoreX used its standard design');
});

it('says a failing brand filter left the colours from Settings, not that a design failed', function () {
    $note = designFailureNotes(new EmailDesignFailedEvent('corex_email_brand', EmailDesignFailedEvent::BRAND_THREW, 'The email colour "text" must be written as #rrggbb.'))[0];

    expect($note->rendered['body'])->toContain('corex_email_brand')
        ->and($note->rendered['body'])->toContain('colours and logo from Settings')
        ->and($note->rendered['body'])->toContain('must be written as #rrggbb')
        ->and($note->rendered['body'])->not->toContain('standard CoreX design');
});

it('keeps its key within what the store holds, however long a class is named', function () {
    $note = designFailureNotes(new EmailDesignFailedEvent(str_repeat('VeryLongNamespace\\', 20) . 'Design', EmailDesignFailedEvent::EMPTY))[0];

    expect(strlen($note->dedupKey))->toBeLessThanOrEqual(191);
});

it('keys a failing design by the design, so that it is one condition however many emails it fails', function () {
    $notes = designFailureNotes(
        new EmailDesignFailedEvent('Acme', EmailDesignFailedEvent::EMPTY),
        new EmailDesignFailedEvent('Acme', EmailDesignFailedEvent::EMPTY),
        new EmailDesignFailedEvent('Other', EmailDesignFailedEvent::EMPTY),
    );

    expect(array_unique(array_map(static fn ($note): string => $note->dedupKey, $notes)))
        ->toBe([0 => 'email.design_failed:Acme', 2 => 'email.design_failed:Other']);
});
