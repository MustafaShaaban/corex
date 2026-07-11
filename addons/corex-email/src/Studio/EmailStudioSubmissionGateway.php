<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Email\Message\EmailMessage;
use Corex\Mail\MailResult;
use Corex\Mail\SubmissionEmailGateway;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Uuid;
use DomainException;

/**
 * Email Studio adapter for permission-scoped submission reply, resend, and log actions.
 */
final readonly class EmailStudioSubmissionGateway implements SubmissionEmailGateway
{
    public function __construct(
        private EmailStudioRepositories $repositories,
        private EmailStudioService $studio,
        private EmailTemplateService $templates,
        private ConfigInterface $config,
    ) {
    }

    public function reply(string $recipient, string $subject, string $htmlBody): MailResult
    {
        return $this->studio->send(
            new EmailMessage([$recipient], [], [], null, $subject, $htmlBody),
            $this->deliveryContext(),
        );
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
