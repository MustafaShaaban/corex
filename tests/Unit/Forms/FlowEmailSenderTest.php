<?php

/**
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Submission\FlowEmailAddressResolver;
use Corex\Forms\Submission\FlowEmailSender;
use Corex\Mail\MailResult;
use Corex\Mail\RoutedMailer;
use Corex\Mail\TemplateMailer;

function successfulFlowMailResult(): MailResult
{
    return new MailResult(
        'e27bc407-9fa1-4865-bab0-9fe18fa31285',
        'f0aa4d44-ff4b-4fb3-b0a5-7ee65931e621',
        MailResult::STATE_CAPTURED,
        'corex-capture',
        'Captured.',
        new DateTimeImmutable('2026-07-04T12:00:00+00:00'),
        false,
    );
}

it('dispatches the bound template with mapped recipients and reply-to', function () {
    $mailer = new class implements RoutedMailer, TemplateMailer {
        public array $bound = [];

        public function dispatch(string $trigger, array $context): ?MailResult
        {
            return null;
        }

        public function dispatchTemplate(
            int $templateId,
            array $recipients,
            ?string $replyTo,
            array $context,
        ): ?MailResult {
            $this->bound = compact('templateId', 'recipients', 'replyTo', 'context');

            return successfulFlowMailResult();
        }
    };
    $sender = new FlowEmailSender($mailer, new FlowEmailAddressResolver());
    $context = [
        'submission' => ['email' => 'visitor@example.com'],
        'flow' => ['owner_email' => 'owner@example.com'],
    ];

    $result = $sender->send([
        'event' => 'submitter_confirmation',
        'template_id' => 17,
        'recipient' => 'submission.email, team@example.com',
        'reply_to' => 'flow.owner_email',
    ], 'contact', $context);

    expect($result?->state)->toBe(MailResult::STATE_CAPTURED)
        ->and($mailer->bound['templateId'])->toBe(17)
        ->and($mailer->bound['recipients'])->toBe(['visitor@example.com', 'team@example.com'])
        ->and($mailer->bound['replyTo'])->toBe('owner@example.com');
});

it('falls back to trigger routing when the mailer has no template-binding capability', function () {
    $mailer = new class implements RoutedMailer {
        public string $trigger = '';

        public function dispatch(string $trigger, array $context): ?MailResult
        {
            $this->trigger = $trigger;

            return successfulFlowMailResult();
        }
    };
    $sender = new FlowEmailSender($mailer, new FlowEmailAddressResolver());

    $sender->send(['event' => 'team_notification'], 'contact', ['submission' => []]);

    expect($mailer->trigger)->toBe('forms.contact.team_notification');
});

it('refuses a bound template when no mapping resolves to a valid recipient', function () {
    $mailer = new class implements RoutedMailer, TemplateMailer {
        public function dispatch(string $trigger, array $context): ?MailResult
        {
            return successfulFlowMailResult();
        }

        public function dispatchTemplate(int $templateId, array $recipients, ?string $replyTo, array $context): ?MailResult
        {
            throw new RuntimeException('Must not dispatch an empty recipient set.');
        }
    };
    $sender = new FlowEmailSender($mailer, new FlowEmailAddressResolver());

    expect($sender->send([
        'event' => 'submitter_confirmation',
        'template_id' => 17,
        'recipient' => 'submission.missing',
    ], 'contact', ['submission' => []]))->toBeNull();
});
