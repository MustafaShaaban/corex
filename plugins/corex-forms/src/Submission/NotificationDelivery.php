<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Mail\MailResult;
use DateTimeImmutable;

/**
 * The truthful outcome of one submission-notification attempt.
 *
 * It exists to stop the old habit of flattening every result into "sent". The vocabulary is the
 * canonical {@see MailResult} state set — reused, not reinvented — plus `not_attempted` (nothing
 * was required or possible) and `unavailable` (a submission saved before delivery was tracked).
 *
 * Two rules it enforces by construction:
 * - `wp_mail()` returning true maps to `accepted`, never `sent`: a transport taking a message is
 *   not proof it reached an inbox (FR-015).
 * - the persisted projection carries only safe fields — no SMTP host, credential, path, or trace
 *   (FR-019). The `safeReason` comes from {@see MailResult::$message}, which its own contract
 *   guarantees secret-free.
 */
final class NotificationDelivery
{
    public const STATUS_NOT_ATTEMPTED = 'not_attempted';
    public const STATUS_UNAVAILABLE   = 'unavailable';

    private function __construct(
        public readonly string $status,
        public readonly ?string $attemptId,
        public readonly ?string $provider,
        public readonly ?DateTimeImmutable $attemptedAt,
        public readonly bool $retryable,
        public readonly string $safeReason,
        public readonly string $reasonCode,
    ) {
    }

    public static function fromResult(MailResult $result): self
    {
        return new self(
            status: $result->state,
            attemptId: $result->attemptId,
            provider: $result->provider,
            attemptedAt: $result->occurredAt,
            retryable: $result->retryable,
            safeReason: $result->message,
            reasonCode: $result->state,
        );
    }

    public static function notAttempted(string $reasonCode, string $reason): self
    {
        return new self(self::STATUS_NOT_ATTEMPTED, null, null, null, false, $reason, $reasonCode);
    }

    /**
     * Routing, policy, or configuration prevented an attempt — distinct from a failure of one
     * (FR-014). Used when the routed path resolves a binding to no deliverable recipient.
     */
    public static function rejected(string $reasonCode, string $reason): self
    {
        return new self(MailResult::STATE_REJECTED, null, null, new DateTimeImmutable('now'), false, $reason, $reasonCode);
    }

    /**
     * The `wp_mail()` floor. A true return is acceptance for delivery — `accepted`, never `sent`.
     */
    public static function wpMail(bool $accepted, string $attemptId, string $reason = ''): self
    {
        if ($accepted) {
            return new self(
                MailResult::STATE_ACCEPTED,
                $attemptId,
                'wp-mail',
                new DateTimeImmutable('now'),
                false,
                $reason !== '' ? $reason : __('WordPress accepted the notification for delivery.', 'corex'),
                MailResult::STATE_ACCEPTED,
            );
        }

        return new self(
            MailResult::STATE_FAILED,
            $attemptId,
            'wp-mail',
            new DateTimeImmutable('now'),
            true,
            $reason !== '' ? $reason : __('WordPress could not send the notification.', 'corex'),
            MailResult::STATE_FAILED,
        );
    }

    /** A submission saved before delivery was tracked — honestly unknown, never assumed successful. */
    public static function unavailable(): self
    {
        return new self(
            self::STATUS_UNAVAILABLE,
            null,
            null,
            null,
            false,
            __('This submission predates delivery tracking; its notification outcome is unavailable.', 'corex'),
            self::STATUS_UNAVAILABLE,
        );
    }

    public function successful(): bool
    {
        return in_array($this->status, [
            MailResult::STATE_ACCEPTED,
            MailResult::STATE_CAPTURED,
            MailResult::STATE_QUEUED,
            MailResult::STATE_SENT,
            MailResult::STATE_OPENED,
        ], true);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'status'       => $this->status,
            'attempt_id'   => $this->attemptId,
            'provider'     => $this->provider,
            'attempted_at' => $this->attemptedAt?->format(DATE_ATOM),
            'retryable'    => $this->retryable,
            'safe_reason'  => $this->safeReason,
            'reason_code'  => $this->reasonCode,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $attemptedAt = isset($data['attempted_at']) && is_string($data['attempted_at'])
            ? (DateTimeImmutable::createFromFormat(DATE_ATOM, $data['attempted_at']) ?: null)
            : null;

        return new self(
            status: (string) ($data['status'] ?? self::STATUS_UNAVAILABLE),
            attemptId: isset($data['attempt_id']) ? (string) $data['attempt_id'] : null,
            provider: isset($data['provider']) ? (string) $data['provider'] : null,
            attemptedAt: $attemptedAt,
            retryable: (bool) ($data['retryable'] ?? false),
            safeReason: (string) ($data['safe_reason'] ?? ''),
            reasonCode: (string) ($data['reason_code'] ?? ($data['status'] ?? self::STATUS_UNAVAILABLE)),
        );
    }
}
