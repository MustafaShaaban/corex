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
        ->and($note->rendered['body'])->toContain('sent in the standard CoreX design')
        ->and($note->rendered['body'])->toContain('AcmeSite\Mail\AcmeEmailDesign')
        ->and($note->recipient->canBeSeenBy(9, fn (string $ability): bool => $ability === CorexAbility::MANAGE_EMAIL))->toBeTrue()
        ->and($note->recipient->canBeSeenBy(9, fn (string $ability): bool => false))->toBeFalse();
});

it('says what the design raised when it stopped with an error', function () {
    $note = designFailureNotes(new EmailDesignFailedEvent('Acme', EmailDesignFailedEvent::THREW, 'Undefined variable $logo'))[0];

    expect($note->rendered['body'])->toContain('It stopped with an error: Undefined variable $logo.');
});

it('is one notification for one failing design, however many emails it fails', function () {
    $notes = designFailureNotes(
        new EmailDesignFailedEvent('Acme', EmailDesignFailedEvent::EMPTY),
        new EmailDesignFailedEvent('Acme', EmailDesignFailedEvent::EMPTY),
        new EmailDesignFailedEvent('Other', EmailDesignFailedEvent::EMPTY),
    );

    expect(array_unique(array_map(static fn ($note): string => $note->dedupKey, $notes)))
        ->toBe([0 => 'email.design_failed:Acme', 2 => 'email.design_failed:Other']);
});
