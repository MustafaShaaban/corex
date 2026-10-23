<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Email\Design\EmailDesignFailedEvent;
use Corex\Email\Design\EmailKind;
use Corex\Email\MailService;
use Corex\Email\Routing\EmailRouteMessageFactory;
use Corex\Events\EventDispatcher;
use Corex\Mail\Capture\MailPath;
use Corex\Mail\DesignedEmails;
use Corex\Mail\MailResult;
use Corex\Support\Uuid;
use DateTimeImmutable;
use DomainException;
use Throwable;

/**
 * Email Studio templates that CoreX code sends by slug, through the shared mailer (spec 120,
 * research R8): the newsletter's double opt-in and welcome, the account add-on's password reset
 * and welcome.
 *
 * The shared mailer and not Email Studio's own sending rule, because Email Studio blocks a
 * production site with no provider set, which is how sites run that send WordPress's own
 * emails today: a password reset must not stop being sent because it was designed.
 */
final readonly class StudioDesignedEmails implements DesignedEmails
{
    /** How long the record of a template that could not be drawn is kept. */
    private const RECORD_DAYS = 90;

    public function __construct(
        private EmailStudioRepositories $repositories,
        private EmailTemplateService $editor,
        private DefaultEmailTemplates $defaults,
        private MailService $mail,
        private MailPath $paths,
        private EventDispatcher $events,
        /** The activity log, where corex-config keeps one. */
        private ?ActivityService $activity = null,
    ) {
    }

    public function send(string $slug, array $to, array $context, string $path): ?MailResult
    {
        $template = $this->repositories->templates->findBySlug($slug);
        if ($template === null || $template->status !== EmailTemplate::STATUS_ACTIVE || $template->activeVersion < 1) {
            return null;
        }
        $version = $this->repositories->templates->findVersion($template->id, $template->activeVersion);
        if ($version === null) {
            return null;
        }
        $layout = $this->repositories->layouts->findVersion($version->layoutId, $version->layoutVersion);
        $kind = $this->defaults->definition($slug)?->kind ?? EmailKind::of($context['email']['kind'] ?? null);

        try {
            $rendered = $this->editor->render($version, EmailRouteMessageFactory::withSiteContext($context, array_values($to)), $layout, $kind);
        } catch (DomainException $error) {
            $this->fellBack($slug, $template->name, $error->getMessage());

            return null;
        }

        $message = EmailRouteMessageFactory::message($version, array_values($to), null, $rendered);
        $result = $this->paths->during($path, fn () => $this->mail->deliver($message));

        return new MailResult(
            attemptId: Uuid::v4(),
            requestId: Uuid::v4(),
            state: $result->status,
            provider: 'corex-mail',
            message: $result->message,
            occurredAt: new DateTimeImmutable('now'),
            retryable: $result->status === MailResult::STATE_FAILED,
            logId: $result->logId,
        );
    }

    /**
     * Says, where somebody who manages email will see it, that a template could not be drawn and
     * the sender's own email went in its place.
     */
    private function fellBack(string $slug, string $name, string $why): void
    {
        $this->events->dispatch(new EmailDesignFailedEvent($slug, EmailDesignFailedEvent::TEMPLATE_FAILED, mb_substr($why, 0, 200)));
        if ($this->activity === null) {
            return;
        }

        $now = new DateTimeImmutable('now');
        try {
            $this->activity->record(
                actorId: 0,
                actorKind: ActivityEvent::ACTOR_SYSTEM,
                actorLabel: __('System', 'corex'),
                area: ActivityEvent::AREA_EMAIL,
                kind: 'email.template.fell_back',
                targetType: 'email_template',
                targetId: $slug,
                targetLabel: $name,
                outcome: ActivityEvent::OUTCOME_FAILURE,
                summary: ['key' => 'email.template.fell_back', 'args' => ['template' => $name, 'reason' => $why]],
                context: ['slug' => $slug, 'reason' => $why],
                sensitivity: ActivityEvent::SENSITIVITY_PUBLIC_ADMIN,
                retentionUntil: $now->modify('+' . self::RECORD_DAYS . ' days'),
                occurredAt: $now,
            );
        } catch (Throwable) {
            // The email has gone in its sender's own design; a record that cannot be written
            // must not stop the person who asked for it.
        }
    }
}
