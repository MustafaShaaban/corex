<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Email\Delivery\DeliveryDecision;
use Corex\Email\Delivery\DeliveryPolicy;
use Corex\Email\Delivery\EmailAttempt;
use Corex\Email\Design\ReplyComposer;
use Corex\Email\Message\EmailMessage;
use Corex\Mail\MessageId;
use Corex\Mail\MailResult;
use Corex\Mail\ReplyConversation;
use Corex\Mail\ReplyReadiness;
use Corex\Mail\SubmissionEmailGateway;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Uuid;
use DomainException;

/**
 * Email Studio adapter for permission-scoped submission reply, resend, and log actions.
 */
final readonly class EmailStudioSubmissionGateway implements SubmissionEmailGateway
{
    private const AN_ATTEMPT_ID = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i';

    public function __construct(
        private EmailStudioRepositories $repositories,
        private EmailStudioService $studio,
        private EmailTemplateService $templates,
        private ConfigInterface $config,
        private ReplyComposer $composer,
    ) {
    }

    public function readiness(): ReplyReadiness
    {
        $decision = $this->studio->decide($this->deliveryContext());
        $mode = match ($decision->action) {
            DeliveryDecision::ACTION_DELIVER => ReplyReadiness::DELIVER,
            DeliveryDecision::ACTION_CAPTURE => ReplyReadiness::CAPTURE,
            default => ReplyReadiness::REFUSE,
        };

        $reason = $this->reasonFor($decision->code);
        // A refusal is never left unexplained: an outcome this class has no sentence for yet
        // is said in the policy's own.
        if ($reason === '' && $mode !== ReplyReadiness::DELIVER) {
            $reason = $decision->reason;
        }

        return new ReplyReadiness($mode, $decision->code, $reason);
    }

    /**
     * The policy's outcome, said to somebody about to answer a customer.
     *
     * The policy's own sentences are written for Email Studio's health panel ("Production live
     * delivery must be deliberately enabled after provider setup"). The person at the reply box
     * needs to know one thing, whether the customer will get this, and where that is changed.
     */
    private function reasonFor(string $code): string
    {
        return match ($code) {
            DeliveryPolicy::NO_PROVIDER => __('Replies are not being delivered: no delivery provider is chosen in CoreX → Settings → Mail.', 'corex'),
            DeliveryPolicy::LIVE_OFF => __('Replies are not being delivered: live delivery is switched off in CoreX → Settings → Mail.', 'corex'),
            DeliveryPolicy::UNKNOWN_ENVIRONMENT => __('Replies are not being delivered: this site does not say whether it is a production, staging or development site, so its email is held back.', 'corex'),
            DeliveryPolicy::DEVELOPMENT => __('This is a development site. A reply is kept in Email Studio and is not delivered to the person who wrote.', 'corex'),
            DeliveryPolicy::STAGING_CAPTURE => __('This is a staging site with live delivery off. A reply is kept in Email Studio and is not delivered to the person who wrote.', 'corex'),
            default => '',
        };
    }

    /**
     * An operator's manual reply from the Submissions inbox.
     *
     * The operator's text used to be the *entire* message body, and `replyTo` was hard-coded null —
     * while {@see resend()} directly below looked up the template version and layout and rendered
     * through them. So every automated email a site sent was branded and every manual reply arrived
     * unstyled on the client's default background, ignoring the configured reply-to. Reported from
     * a real build (issue #138, item 3).
     *
     * The message is drawn in the site's email design, with the same words beside it as plain
     * text (spec 106). It is not run through an Email Studio template: there is no template for
     * "whatever the operator typed", and one would put a placeholder between the operator and
     * their own words.
     */
    public function reply(string $recipient, string $subject, string $htmlBody, ?ReplyConversation $within = null): MailResult
    {
        $email = $this->composer->compose($recipient, $subject, $htmlBody);

        return $this->studio->send(
            new EmailMessage(
                [$recipient],
                [],
                [],
                $this->replyToAddress(),
                $subject,
                $email->html,
                // An email says which one it answers, and names the ones before it: that is
                // what a mail program reads to put it under them (spec 106, FR-040).
                $within === null || $within->isEmpty() ? [] : [
                    'In-Reply-To' => $within->latest(),
                    'References' => implode(' ', $within->messageIds),
                ],
                plainText: $email->text,
            ),
            $this->deliveryContext(),
        );
    }

    /**
     * Among the attempts named, the emails this person was sent and has: each went to them,
     * left or was kept, and was sent under an id that can be named. Oldest first.
     *
     * An email sent before CoreX chose ids is on record and cannot be continued: it left
     * under an id nothing kept.
     */
    public function conversation(string $recipient, array $attemptIds): ReplyConversation
    {
        $sent = [];
        foreach (array_unique(array_filter($attemptIds, 'is_string')) as $attemptId) {
            // An id is asked for only if it is one: the list comes from what a submission
            // has stored, and the repository refuses anything else by throwing.
            $attempt = preg_match(self::AN_ATTEMPT_ID, $attemptId) === 1
                ? $this->repositories->attempts->findByAttemptId($attemptId)
                : null;
            if (
                $attempt !== null
                && $this->repositories->attempts->wasSentTo($attempt, $recipient)
                && in_array($attempt->state, [EmailAttempt::STATE_SENT, EmailAttempt::STATE_CAPTURED, EmailAttempt::STATE_OPENED], true)
                && MessageId::isOne($attempt->providerMessageId)
            ) {
                $sent[] = $attempt;
            }
        }
        // By when each left, and for two in the same second by the order they were recorded in.
        usort($sent, static fn (EmailAttempt $a, EmailAttempt $b): int => [$a->occurredAt, $a->id] <=> [$b->occurredAt, $b->id]);

        return new ReplyConversation(
            array_map(static fn (EmailAttempt $attempt): string => (string) $attempt->providerMessageId, $sent),
            $sent === [] ? '' : $sent[count($sent) - 1]->subject,
        );
    }

    public function preview(string $recipient, string $subject, string $htmlBody): array
    {
        $email = $this->composer->compose($recipient, $subject, $htmlBody);

        return ['html' => $email->html, 'text' => $email->text];
    }

    /**
     * The configured reply-to, or null.
     *
     * Validated before it reaches a header. Null when nothing is configured, which is what the
     * message carried before — the difference is that it is now the answer to a question rather
     * than a hard-coded constant.
     */
    private function replyToAddress(): ?string
    {
        $configured = trim((string) $this->config->get('mail.reply_to', ''));

        return $configured !== '' && is_email($configured) !== false
            ? sanitize_email($configured)
            : null;
    }

    public function resend(string $attemptId, string $recipient, array $context): MailResult
    {
        $attempt = $this->repositories->attempts->findByAttemptId($attemptId);
        if ($attempt === null || $attempt->templateId === null || $attempt->templateVersion === null) {
            throw new DomainException(__('The related email cannot be safely reconstructed.', 'corex'));
        }
        $version = $this->repositories->templates->findVersion($attempt->templateId, $attempt->templateVersion);
        if ($version === null) {
            throw new DomainException(__('The related email template version is unavailable.', 'corex'));
        }
        $layout = $this->repositories->layouts->findVersion($version->layoutId, $version->layoutVersion);
        $rendered = $this->templates->render($version, $context, $layout);

        return $this->studio->resend(
            $attemptId,
            new EmailMessage([$recipient], [], [], null, $rendered['subject'], $rendered['html']),
            $this->deliveryContext(),
        );
    }

    public function log(string $attemptId): ?array
    {
        $attempt = $this->repositories->attempts->findByAttemptId($attemptId);
        if ($attempt === null) {
            return null;
        }

        return [
            'attempt_id' => $attempt->attemptId,
            'parent_attempt_id' => $attempt->parentAttemptId,
            'recipient' => $attempt->recipient,
            'subject' => $attempt->subject,
            'state' => $attempt->state,
            'provider' => $attempt->provider,
            'provider_event' => $attempt->providerEvent,
            'retryable' => $attempt->retryable,
            'occurred_at' => $attempt->occurredAt->format(DATE_ATOM),
            'template_id' => $attempt->templateId,
            'template_version' => $attempt->templateVersion,
        ];
    }

    private function deliveryContext(): EmailDeliveryContext
    {
        $environment = trim((string) $this->config->get('app.env', ''));
        $provider = sanitize_key((string) $this->config->get('mail.provider', ''));

        return new EmailDeliveryContext(
            $environment !== '' ? $environment : wp_get_environment_type(),
            $this->studio->supportsProvider($provider),
            filter_var($this->config->get('mail.live_delivery', false), FILTER_VALIDATE_BOOL),
            Uuid::v4(),
        );
    }
}
