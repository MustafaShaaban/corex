<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission\Stages;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\EmailVariableValues;
use Corex\Forms\Submission\FlowEmailAddressResolver;
use Corex\Forms\Submission\FlowEmailSender;
use Corex\Forms\Submission\NotificationDelivery;
use Corex\Forms\Submission\NotificationDispatcher;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionRepository;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;
use Corex\Mail\MailRequest;
use Corex\Mail\MailResult;

/**
 * Delivers the submission notification and records a truthful outcome.
 *
 * When CoreX Mail is active, each enabled Email Studio binding is routed and its per-event result
 * kept in `corex_email_json` as before. When it is inactive, the pipeline no longer goes silent:
 * it falls back to the wp_mail() floor through {@see NotificationDispatcher} (FR-017). Either way a
 * single headline {@see NotificationDelivery} is projected to `corex_notification_delivery`, so an
 * administrator can see whether the notification was accepted, captured, queued, rejected, or
 * failed — never a saved submission with an unknown fate.
 */
final readonly class EmailStage implements SubmissionStage
{
    public function __construct(
        private FlowEmailSender $sender,
        private SubmissionRepository $submissions,
        private NotificationDispatcher $dispatcher,
        private FlowEmailAddressResolver $addresses,
        private EmailVariableValues $variables,
    ) {
    }

    public function key(): string
    {
        return 'email';
    }

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult
    {
        $bindings = array_values(array_filter(
            $context->version->configuration->emailRoutes,
            static fn (mixed $binding): bool => is_array($binding) && ($binding['enabled'] ?? false),
        ));
        $primary = array_values(array_filter(
            $bindings,
            static fn (array $binding): bool => ($binding['event'] ?? '') !== 'admin_failure',
        ));
        if ($primary === []) {
            $delivery = NotificationDelivery::notAttempted(
                'no_binding',
                __('No notification is configured for this form.', 'corex'),
            );
            $next = $context->withMetadata([
                'email' => ['state' => 'not_configured', 'bindings' => []],
                'notification_delivery' => $delivery->toArray(),
            ]);
            $this->persist($next);

            return SubmissionStageResult::success($this->key(), $next, __('No flow email binding is enabled.', 'corex'));
        }

        // CoreX Mail inactive: no Email Studio to route through — fall back to the wp_mail() floor
        // instead of recording 'unrouted' and sending nothing (the pre-feature silent no-send).
        if (! $this->sender->canRoute()) {
            return $this->deliverViaFallback($context, $primary);
        }

        $outcomes = [];
        $failed = null;
        $retryable = false;
        $headline = null;
        foreach ($primary as $binding) {
            $mailContext = $this->mailContext($context);
            $result = $this->sender->send($binding, $context->flow->slug, $mailContext);
            $event = (string) ($binding['event'] ?? 'submitted');
            $outcomes[$event] = $this->outcome($result, (int) ($binding['template_id'] ?? 0), $this->sender->recipientsFor($binding, $mailContext))
                + $this->routedNote($binding, $context);
            $headline = $this->promoteHeadline($headline, $this->deliveryFor($result));
            if ($result === null || ! $result->successful()) {
                $failed ??= $result?->message ?? __('The enabled flow email binding could not be resolved.', 'corex');
                $retryable = $retryable || ($result?->retryable ?? false);
            }
        }
        if ($failed !== null) {
            $outcomes += $this->failureAlerts($bindings, $context, $failed);
        }
        $next = $context->withMetadata([
            'email' => [
                'state' => $failed === null ? 'complete' : 'failed',
                'bindings' => $outcomes,
            ],
            'notification_delivery' => ($headline ?? NotificationDelivery::notAttempted(
                'no_binding',
                __('No notification is configured for this form.', 'corex'),
            ))->toArray(),
        ]);
        $this->persist($next);

        return $failed === null
            ? SubmissionStageResult::success($this->key(), $next, $context->isTest
                ? __('The emails were held for the test and not sent. They are in the Email Studio log.', 'corex')
                : __('Flow email bindings completed.', 'corex'))
            : SubmissionStageResult::failure($this->key(), $next, $failed, $retryable);
    }

    /**
     * Send one basic notification through the wp_mail() floor. Reached only when CoreX Mail is
     * inactive, so no Email Studio template applies — the visitor's own values are the body.
     *
     * @param list<array<string,mixed>> $primary
     */
    private function deliverViaFallback(SubmissionPipelineContext $context, array $primary): SubmissionStageResult
    {
        // A marked test sends nothing, and without CoreX Mail there is nowhere to hold the email
        // (spec 113, US3): it is not written at all.
        if ($context->isTest) {
            $next = $context->withMetadata([
                'email' => ['state' => 'held', 'bindings' => []],
                'notification_delivery' => NotificationDelivery::notAttempted(
                    'test_not_sent',
                    __('A marked test sends no email.', 'corex'),
                )->toArray(),
            ]);
            $this->persist($next);

            return SubmissionStageResult::success(
                $this->key(),
                $next,
                __('Not sent: a marked test sends no email, and CoreX Mail is not active to keep a copy.', 'corex'),
            );
        }

        $mailContext = $this->mailContext($context);
        $recipients = $this->addresses->recipients((string) ($primary[0]['recipient'] ?? ''), $mailContext);
        if ($recipients === []) {
            $adminEmail = (string) get_option('admin_email');
            $recipients = $adminEmail !== '' ? [$adminEmail] : [];
        }

        // Handed over as what writes it: the dispatcher asks in the email's language. A flow's
        // test send is made from the admin, in the language of whoever is testing.
        $write = static fn (): MailRequest => new MailRequest(
            to: $recipients,
            subject: sprintf(
                /* translators: %s: form slug */
                __('New "%s" form submission', 'corex'),
                $context->flow->slug,
            ),
            body: NotificationDispatcher::plainTextBody($context->values),
        );

        $delivery = $this->dispatcher->dispatch(
            'forms.' . $context->flow->slug . '.submitted',
            $mailContext,
            $write,
        );

        $next = $context->withMetadata([
            'email' => [
                'state' => $delivery->successful() ? 'complete' : 'failed',
                'bindings' => ['submitted' => [
                    'template_id' => 0,
                    'state' => $delivery->status,
                    'attempt_id' => $delivery->attemptId,
                    'provider' => $delivery->provider,
                    'retryable' => $delivery->retryable,
                    'recipients' => $recipients,
                ]],
            ],
            'notification_delivery' => $delivery->toArray(),
        ]);
        $this->persist($next);

        return $delivery->successful()
            ? SubmissionStageResult::success($this->key(), $next, __('Notification sent through the WordPress mail path.', 'corex'))
            : SubmissionStageResult::failure($this->key(), $next, $delivery->safeReason, $delivery->retryable);
    }

    /** Map a per-binding routed result to a headline delivery (a null result means no recipient). */
    private function deliveryFor(?MailResult $result): NotificationDelivery
    {
        return $result !== null
            ? NotificationDelivery::fromResult($result)
            : NotificationDelivery::rejected('no_recipient', __('The notification had no deliverable recipient.', 'corex'));
    }

    /** Keep the first delivery, but let a later failure take over the headline from a success. */
    private function promoteHeadline(?NotificationDelivery $current, NotificationDelivery $candidate): NotificationDelivery
    {
        if ($current === null) {
            return $candidate;
        }

        return (! $candidate->successful() && $current->successful()) ? $candidate : $current;
    }

    /**
     * What a template is filled in from: the answers, the flow, the routing, and every registered
     * email variable (spec 122, FR-023).
     *
     * @return array<string,mixed>
     */
    private function mailContext(SubmissionPipelineContext $context): array
    {
        return $this->variables->into([
            'submission' => $context->values,
            'flow' => [
                'id' => $context->flow->id,
                'slug' => $context->flow->slug,
                'owner_id' => $context->flow->ownerId,
            ],
            'routing' => $context->metadata['routing'] ?? [],
            'is_test' => $context->isTest,
        ]);
    }

    /**
     * "Routed owner had no address; sent to …", kept with the delivery of an email addressed to
     * the routed owner whose routing had to use the fallback recipient (spec 122, FR-018).
     *
     * @param array<string,mixed> $binding
     * @return array{note?:string}
     */
    private function routedNote(array $binding, SubmissionPipelineContext $context): array
    {
        $note = (string) ($context->metadata['routing']['recipients']['note'] ?? '');

        return $note !== '' && FlowEmailAddressResolver::namesRoutedOwner((string) ($binding['recipient'] ?? ''))
            ? ['note' => $note]
            : [];
    }

    /**
     * What is kept of one email of the flow. Its recipients are kept with the submission, so they
     * follow its retention, trash, delete and personal-data export, and a resend reaches them
     * (spec 112, FR-032).
     *
     * @param list<string> $recipients
     *
     * @return array<string,mixed>
     */
    private function outcome(?MailResult $result, int $templateId, array $recipients): array
    {
        return [
            'template_id' => $templateId,
            'state' => $result?->state ?? 'unrouted',
            'attempt_id' => $result?->attemptId,
            'provider' => $result?->provider,
            'retryable' => $result?->retryable ?? false,
            'recipients' => $result === null ? [] : $recipients,
        ];
    }

    /**
     * @param list<array<string,mixed>> $bindings
     * @return array<string,array<string,mixed>>
     */
    private function failureAlerts(array $bindings, SubmissionPipelineContext $context, string $message): array
    {
        $outcomes = [];
        foreach ($bindings as $binding) {
            if (($binding['event'] ?? '') !== 'admin_failure') {
                continue;
            }
            $mailContext = $this->mailContext($context);
            $mailContext['failure'] = ['message' => $message];
            $result = $this->sender->send($binding, $context->flow->slug, $mailContext);
            $outcomes['admin_failure'] = $this->outcome($result, (int) ($binding['template_id'] ?? 0), $this->sender->recipientsFor($binding, $mailContext));
        }

        return $outcomes;
    }

    private function persist(SubmissionPipelineContext $context): void
    {
        if ($context->submissionId !== null) {
            $this->submissions->updatePipelineMetadata($context->submissionId, $context->metadata);
        }
    }
}
