<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\DeliveryDecision;
use Corex\Email\Delivery\DeliveryPolicy;
use Corex\Email\Delivery\EmailAttempt;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Driver\MailDriver;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Security\HeaderGuard;
use Corex\Events\EventDispatcher;
use Corex\Mail\Capture\CapturedMessage;
use Corex\Mail\Capture\MailHold;
use Corex\Mail\Capture\MailPath;
use Corex\Mail\MailResult;
use Corex\Mail\MessageId;
use Corex\Support\Uuid;
use DateTimeImmutable;
use DomainException;
use Throwable;

/**
 * Coordinates safe test delivery, resend lineage, attempts, capture, and health.
 */
final class EmailStudioService
{
    /** The decision code of a send held by a flow's marked test. */
    public const HELD_FOR_TEST = 'held_for_test';

    // Explicit PSR-11 boundaries stay visible here; a dependency bag would hide replaceable collaborators.
    public function __construct(
        private readonly DeliveryPolicy $policy,
        private readonly CapturedEmailRepository $captures,
        private readonly EmailAttemptRepository $attempts,
        private readonly MailDriver $providerDriver,
        private readonly EmailTemplateService $templates,
        private readonly string $providerName,
        private readonly ?EventDispatcher $events = null,
        /**
         * The host an email's id is made of: the site's own. '' sends as before, under whatever
         * id WordPress's mailer makes, which nothing can read back.
         */
        private readonly string $messageHost = '',
        /** Names a captured email for the log (spec 113). */
        private readonly ?MailPath $paths = null,
        /** Holds every email of a flow's marked test, on any site (spec 113, US3). */
        private readonly ?MailHold $hold = null,
        /** The header checks every other CoreX email passes (spec 120). */
        private readonly ?HeaderGuard $guard = null,
    ) {
    }

    public function testSend(
        EmailMessage $message,
        EmailDeliveryContext $delivery,
        ?EmailTemplateReference $template = null,
    ): MailResult {
        return $this->dispatch(
            $message,
            $delivery,
            new EmailDispatchMetadata($template, null, 'test'),
        );
    }

    public function send(
        EmailMessage $message,
        EmailDeliveryContext $delivery,
        ?EmailTemplateReference $template = null,
    ): MailResult {
        return $this->dispatch(
            $message,
            $delivery,
            new EmailDispatchMetadata($template, null, 'route'),
        );
    }

    /**
     * What a message sent in this context would meet: delivery, capture, or a refusal.
     *
     * Public so that whoever is about to offer a send can say what it will do, from the decision
     * the send itself takes (spec 106, FR-029).
     */
    public function decide(EmailDeliveryContext $delivery): DeliveryDecision
    {
        if ($this->hold?->active() === true) {
            return new DeliveryDecision(
                action: DeliveryDecision::ACTION_CAPTURE,
                reason: __('A marked test holds its emails: they are kept here and never sent.', 'corex'),
                providerRequired: false,
                code: self::HELD_FOR_TEST,
            );
        }

        return $this->policy->evaluate(
            $delivery->environment,
            $delivery->providerConfigured,
            $delivery->liveDeliveryEnabled,
        );
    }

    public function supportsProvider(string $configuredProvider): bool
    {
        return $configuredProvider !== '' && hash_equals($this->providerName, $configuredProvider);
    }

    public function resend(
        string $attemptId,
        EmailMessage $message,
        EmailDeliveryContext $delivery,
    ): MailResult {
        $previous = $this->attempts->findByAttemptId($attemptId);
        if ($previous === null || ! $previous->retryable) {
            throw new DomainException(__('Only a retryable email attempt can be resent.', 'corex'));
        }

        return $this->dispatch(
            $message,
            $delivery,
            new EmailDispatchMetadata(
                $previous->templateSlug === null ? null : new EmailTemplateReference(
                    $previous->templateSlug,
                    $previous->templateId,
                    $previous->templateVersion,
                    $previous->routeId,
                ),
                $previous->attemptId,
                'resend',
            ),
        );
    }

    /** @return array<string,string> */
    public function health(EmailTemplateVersion $version, EmailHealthContext $context): array
    {
        $errors = $this->templates->validateDraft($version);
        $subscriptionContent = $version->htmlBody . implode('', $context->layout?->regions ?? []);

        if ($version->plainTextMode === EmailTemplateVersion::PLAIN_MANUAL && trim($version->plainText) === '') {
            $errors['plainText'] = __('Manual plain text is required.', 'corex');
        }

        if ($context->requiresSubscriptionLinks && stripos($subscriptionContent, 'unsubscribe') === false) {
            $errors['unsubscribe'] = __('An unsubscribe link is required.', 'corex');
        }

        if ($context->requiresSubscriptionLinks && stripos($subscriptionContent, 'preferences') === false) {
            $errors['preferences'] = __('A preferences link is required.', 'corex');
        }

        if ($context->replyTo !== null && filter_var($context->replyTo, FILTER_VALIDATE_EMAIL) === false) {
            $errors['replyTo'] = __('The reply-to address is invalid.', 'corex');
        }

        if (! $context->providerConfigured) {
            $errors['provider'] = __('A delivery provider is required for live delivery.', 'corex');
        }

        return $errors;
    }

    private function dispatch(
        EmailMessage $message,
        EmailDeliveryContext $delivery,
        EmailDispatchMetadata $metadata,
    ): MailResult {
        $recipients = $this->validRecipients($message);
        if ($recipients === []) {
            return $this->announce($this->missingRecipientResult($delivery, $metadata), $metadata);
        }
        $unsafe = $this->guard?->inspect([
            'subject'   => $message->subject,
            'from'      => $message->from ?? '',
            'from name' => $message->fromName ?? '',
            'reply-to'  => $message->replyTo ?? '',
        ]);
        if ($unsafe !== null) {
            return $this->announce($this->refusedResult($delivery, $metadata, $unsafe), $metadata);
        }

        $decision = $this->decide($delivery);
        $attemptIds = array_map(static fn (): string => Uuid::v4(), $recipients);
        // Sent under an id CoreX chose, so that a later email can say it answers this one
        // (spec 106, FR-037). One email, whoever it goes to: the id is the first attempt's.
        $messageId = MessageId::of($attemptIds[0], $this->messageHost);
        if ($messageId !== '') {
            $message = $message->withHeaders(['Message-ID' => $messageId]);
        }
        $outcome    = $this->execute($decision, $message, $attemptIds[0], $this->pathName($metadata));
        $attempt    = $this->recordAttempts(
            $recipients,
            $attemptIds,
            new EmailAttemptContext($message, $delivery, $metadata, $outcome),
        );

        return $this->announce($this->mailResult($attempt, $outcome), $metadata);
    }

    /**
     * Emit a failure event so the Notification Center can react (spec 072). The event fires for every
     * unsuccessful delivery; whether a particular source (a test send, say) becomes a notification is
     * the consumer's policy, not this service's.
     */
    private function announce(MailResult $result, EmailDispatchMetadata $metadata): MailResult
    {
        if ($this->events !== null && ! $result->successful()) {
            $this->events->dispatch(new EmailStudioDeliveryFailedEvent(
                attemptId: $result->attemptId,
                provider: (string) $result->provider,
                safeReason: $result->message,
                source: $metadata->source,
                retryable: $result->retryable,
            ));
        }

        return $result;
    }

    /** @return list<string> */
    private function validRecipients(EmailMessage $message): array
    {
        return array_values(array_filter(
            $message->to,
            static fn (string $recipient): bool => filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }

    private function missingRecipientResult(
        EmailDeliveryContext $delivery,
        EmailDispatchMetadata $metadata,
    ): MailResult {
        return new MailResult(
            attemptId: Uuid::v4(),
            requestId: $delivery->requestId,
            state: MailResult::STATE_REJECTED,
            provider: 'delivery-policy',
            message: __('No valid recipient was provided.', 'corex'),
            occurredAt: new DateTimeImmutable('now'),
            retryable: false,
            parentAttemptId: $metadata->parentAttemptId,
        );
    }

    private function refusedResult(EmailDeliveryContext $delivery, EmailDispatchMetadata $metadata, string $why): MailResult
    {
        return new MailResult(
            attemptId: Uuid::v4(),
            requestId: $delivery->requestId,
            state: MailResult::STATE_REJECTED,
            provider: 'delivery-policy',
            message: $why,
            occurredAt: new DateTimeImmutable('now'),
            retryable: false,
            parentAttemptId: $metadata->parentAttemptId,
        );
    }

    /**
     * @param list<string> $recipients
     * @param list<string> $attemptIds
     */
    private function recordAttempts(array $recipients, array $attemptIds, EmailAttemptContext $context): EmailAttempt
    {
        $occurredAt   = new DateTimeImmutable('now');
        $firstAttempt = null;
        foreach ($recipients as $index => $recipient) {
            $attempt = $this->recordAttempt($recipient, $attemptIds[$index], $context, $occurredAt);
            $firstAttempt ??= $attempt;
        }

        return $firstAttempt;
    }

    private function recordAttempt(
        string $recipient,
        string $attemptId,
        EmailAttemptContext $context,
        DateTimeImmutable $occurredAt,
    ): EmailAttempt {
        $outcome  = $context->outcome;
        $metadata = $context->metadata;

        return $this->attempts->record(new EmailAttempt(
            id: 0,
            attemptId: $attemptId,
            requestId: $context->delivery->requestId,
            parentAttemptId: $metadata->parentAttemptId,
            recipient: $recipient,
            subject: $context->message->subject,
            templateSlug: $metadata->template?->slug,
            state: $outcome->state,
            provider: $outcome->provider,
            providerEvent: $this->providerEvent($outcome->state),
            retryable: $outcome->retryable,
            occurredAt: $occurredAt,
            source: $metadata->source,
            environment: $this->environment($context->delivery->environment),
            templateId: $metadata->template?->templateId,
            templateVersion: $metadata->template?->templateVersion,
            routeId: $metadata->template?->routeId,
            // The id it was sent under. A provider that answers one of its own would be
            // recorded here in its place; none of CoreX's drivers is told one.
            providerMessageId: MessageId::isOne($context->message->headers['Message-ID'] ?? null)
                ? $context->message->headers['Message-ID']
                : null,
            errorCode: $this->errorCode($outcome),
            missingVariables: $metadata->template === null ? [] : $metadata->template->missingVariables,
        ));
    }

    private function providerEvent(string $state): ?string
    {
        return match ($state) {
            EmailAttempt::STATE_CAPTURED => 'captured',
            EmailAttempt::STATE_SENT     => 'accepted',
            EmailAttempt::STATE_FAILED   => 'failed',
            EmailAttempt::STATE_REJECTED => 'blocked',
            default                      => null,
        };
    }

    private function errorCode(EmailDeliveryOutcome $outcome): ?string
    {
        return match ($outcome->state) {
            EmailAttempt::STATE_FAILED   => $outcome->provider === 'corex-capture' ? 'capture_failed' : 'provider_failed',
            EmailAttempt::STATE_REJECTED => 'delivery_blocked',
            default                      => null,
        };
    }

    private function environment(string $environment): string
    {
        return in_array($environment, ['local', 'development', 'staging', 'production'], true)
            ? $environment
            : 'production';
    }

    /** What the log calls a captured Email Studio send: what sent it, else the kind of send. */
    private function pathName(EmailDispatchMetadata $metadata): string
    {
        return $this->paths?->current() ?? match ($metadata->source) {
            'test'   => __('Email Studio: test send', 'corex'),
            'resend' => __('Email Studio: sent again', 'corex'),
            default  => __('Email Studio: routed email', 'corex'),
        };
    }

    private function mailResult(EmailAttempt $attempt, EmailDeliveryOutcome $outcome): MailResult
    {
        return new MailResult(
            attemptId: $attempt->attemptId,
            requestId: $attempt->requestId,
            state: $outcome->state,
            provider: $outcome->provider,
            message: $outcome->message,
            occurredAt: $attempt->occurredAt,
            retryable: $outcome->retryable,
            logId: $attempt->id,
            parentAttemptId: $attempt->parentAttemptId,
        );
    }

    private function execute(
        DeliveryDecision $decision,
        EmailMessage $message,
        string $attemptId,
        string $path,
    ): EmailDeliveryOutcome
    {
        if ($decision->action === DeliveryDecision::ACTION_BLOCK) {
            return new EmailDeliveryOutcome(EmailAttempt::STATE_REJECTED, 'delivery-policy', $decision->reason, false);
        }

        if ($decision->action === DeliveryDecision::ACTION_CAPTURE) {
            // Persistence crosses WordPress drivers; converting failures to retryable attempts is the recovery contract.
            try {
                $held = $decision->code === self::HELD_FOR_TEST;
                $capture = $this->captures->capture(
                    $message,
                    $attemptId,
                    path: $path,
                    state: $held ? CapturedMessage::STATE_HELD_FOR_TEST : CapturedMessage::STATE_CAPTURED,
                );
                $this->hold?->note($message->to, $message->subject, $capture->captureId, $path);

                return new EmailDeliveryOutcome(EmailAttempt::STATE_CAPTURED, 'corex-capture', $decision->reason, false);
            } catch (Throwable) {
                return new EmailDeliveryOutcome(
                    EmailAttempt::STATE_FAILED,
                    'corex-capture',
                    __('Local email capture failed.', 'corex'),
                    true,
                );
            }
        }

        // Provider drivers are extension points; converting arbitrary failures to typed attempts keeps callers alive.
        try {
            $sent = $this->providerDriver->send($message);
        } catch (Throwable) {
            $sent = false;
        }

        return $sent
            ? new EmailDeliveryOutcome(EmailAttempt::STATE_SENT, $this->providerName, __('The provider accepted the email.', 'corex'), false)
            : new EmailDeliveryOutcome(EmailAttempt::STATE_FAILED, $this->providerName, __('The provider rejected or failed the email.', 'corex'), true);
    }
}
