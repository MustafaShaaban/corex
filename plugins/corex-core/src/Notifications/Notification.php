<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

use Corex\Support\Uuid;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The shared notification record — the *condition*, not one person's view of it. Per-user read/
 * dismiss/snooze state lives in a separate row; resolution (the condition ending) is recorded here.
 *
 * Immutable and secret-free by construction: metadata is rejected if it carries a token, credential,
 * or key (the same discipline as {@see \Corex\Activity\ActivityEvent}). Repeated occurrences of the
 * same condition update one record via its dedup key (FR-011), never a flood of rows.
 */
final class Notification
{
    /**
     * @param array{title:string,body:string} $rendered
     * @param array<string,mixed>              $metadata
     */
    private function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $type,
        public readonly string $category,
        public readonly string $severity,
        public readonly string $sourceModule,
        public readonly ?string $sourceType,
        public readonly ?string $sourceId,
        public readonly string $titleKey,
        public readonly string $messageKey,
        public readonly array $rendered,
        public readonly string $dedupKey,
        public readonly int $occurrences,
        public readonly DateTimeImmutable $firstOccurredAt,
        public readonly DateTimeImmutable $latestOccurredAt,
        public readonly ?DateTimeImmutable $expiresAt,
        public readonly ?DateTimeImmutable $resolvedAt,
        public readonly ?string $resolutionReason,
        public readonly ?string $environment,
        public readonly ?int $actorId,
        public readonly NotificationRecipient $recipient,
        public readonly ?NotificationAction $action,
        public readonly array $metadata,
    ) {
        $this->validate();
    }

    /**
     * @param array{title:string,body:string} $rendered
     * @param array<string,mixed>              $metadata
     */
    public static function create(
        string $type,
        string $category,
        string $severity,
        string $sourceModule,
        string $titleKey,
        string $messageKey,
        array $rendered,
        string $dedupKey,
        NotificationRecipient $recipient,
        DateTimeImmutable $occurredAt,
        ?string $sourceType = null,
        ?string $sourceId = null,
        ?NotificationAction $action = null,
        ?DateTimeImmutable $expiresAt = null,
        ?string $environment = null,
        ?int $actorId = null,
        array $metadata = [],
    ): self {
        return new self(
            id: null,
            uuid: Uuid::v4(),
            type: $type,
            category: $category,
            severity: $severity,
            sourceModule: $sourceModule,
            sourceType: $sourceType,
            sourceId: $sourceId,
            titleKey: $titleKey,
            messageKey: $messageKey,
            rendered: $rendered,
            dedupKey: $dedupKey,
            occurrences: 1,
            firstOccurredAt: $occurredAt,
            latestOccurredAt: $occurredAt,
            expiresAt: $expiresAt,
            resolvedAt: null,
            resolutionReason: null,
            environment: $environment,
            actorId: $actorId,
            recipient: $recipient,
            action: $action,
            metadata: $metadata,
        );
    }

    public function withId(int $id): self
    {
        return $this->copyWith(['id' => $id]);
    }

    /** Record another occurrence of the same condition. */
    public function withOccurrence(DateTimeImmutable $at): self
    {
        return $this->copyWith([
            'occurrences' => $this->occurrences + 1,
            'latestOccurredAt' => $at,
            'resolvedAt' => null,        // a fresh occurrence reopens a resolved condition
            'resolutionReason' => null,
        ]);
    }

    public function resolved(string $reason, DateTimeImmutable $at): self
    {
        return $this->copyWith(['resolvedAt' => $at, 'resolutionReason' => $reason]);
    }

    public function isResolved(): bool
    {
        return $this->resolvedAt !== null;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'uuid'               => $this->uuid,
            'type'               => $this->type,
            'category'           => $this->category,
            'severity'           => $this->severity,
            'source_module'      => $this->sourceModule,
            'source_type'        => $this->sourceType,
            'source_id'          => $this->sourceId,
            'title_key'          => $this->titleKey,
            'message_key'        => $this->messageKey,
            'rendered'           => $this->rendered,
            'dedup_key'          => $this->dedupKey,
            'occurrences'        => $this->occurrences,
            'first_occurred_at'  => $this->firstOccurredAt->format(DATE_ATOM),
            'latest_occurred_at' => $this->latestOccurredAt->format(DATE_ATOM),
            'expires_at'         => $this->expiresAt?->format(DATE_ATOM),
            'resolved_at'        => $this->resolvedAt?->format(DATE_ATOM),
            'resolution_reason'  => $this->resolutionReason,
            'environment'        => $this->environment,
            'actor_id'           => $this->actorId,
            'recipient'          => $this->recipient->toArray(),
            'action'             => $this->action?->toArray(),
            'metadata'           => $this->metadata,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            uuid: (string) ($data['uuid'] ?? Uuid::v4()),
            type: (string) ($data['type'] ?? ''),
            category: (string) ($data['category'] ?? ''),
            severity: (string) ($data['severity'] ?? ''),
            sourceModule: (string) ($data['source_module'] ?? ''),
            sourceType: isset($data['source_type']) ? (string) $data['source_type'] : null,
            sourceId: isset($data['source_id']) ? (string) $data['source_id'] : null,
            titleKey: (string) ($data['title_key'] ?? ''),
            messageKey: (string) ($data['message_key'] ?? ''),
            rendered: self::renderedFrom($data['rendered'] ?? []),
            dedupKey: (string) ($data['dedup_key'] ?? ''),
            occurrences: max(1, (int) ($data['occurrences'] ?? 1)),
            firstOccurredAt: self::dateFrom($data['first_occurred_at'] ?? null),
            latestOccurredAt: self::dateFrom($data['latest_occurred_at'] ?? null),
            expiresAt: self::nullableDate($data['expires_at'] ?? null),
            resolvedAt: self::nullableDate($data['resolved_at'] ?? null),
            resolutionReason: isset($data['resolution_reason']) ? (string) $data['resolution_reason'] : null,
            environment: isset($data['environment']) ? (string) $data['environment'] : null,
            actorId: isset($data['actor_id']) ? (int) $data['actor_id'] : null,
            recipient: NotificationRecipient::fromArray((array) ($data['recipient'] ?? [])),
            action: isset($data['action']) && is_array($data['action']) ? NotificationAction::fromArray($data['action']) : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    private function validate(): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $this->type) !== 1) {
            throw new InvalidArgumentException('Notification type must be a lowercase registered identifier.');
        }
        if (! NotificationCategory::isValid($this->category)) {
            throw new InvalidArgumentException('Unsupported notification category: ' . $this->category);
        }
        if (! NotificationSeverity::isValid($this->severity)) {
            throw new InvalidArgumentException('Unsupported notification severity: ' . $this->severity);
        }
        if ($this->dedupKey === '' || $this->sourceModule === '') {
            throw new InvalidArgumentException('Notification requires a dedup key and a source module.');
        }
        if ($this->occurrences < 1 || $this->latestOccurredAt < $this->firstOccurredAt) {
            throw new InvalidArgumentException('Notification occurrence counters are inconsistent.');
        }
        $this->assertNoSecretKeys($this->metadata);
    }

    /** @param array<mixed> $values */
    private function assertNoSecretKeys(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match('/(?:password|passphrase|secret|token|api[_-]?key|authorization|cookie|private[_-]?key)/i', $key) === 1) {
                throw new InvalidArgumentException('Notification metadata contains a secret-bearing key.');
            }
            if (is_array($value)) {
                $this->assertNoSecretKeys($value);
            }
        }
    }

    /** @param array<string,mixed> $changes */
    private function copyWith(array $changes): self
    {
        $data = $this->toArray();
        // Preserve real DateTimeImmutable instances rather than round-tripping through strings.
        $merged = [...$data, ...$changes];

        return new self(
            id: $merged['id'] ?? null,
            uuid: $this->uuid,
            type: $this->type,
            category: $this->category,
            severity: $this->severity,
            sourceModule: $this->sourceModule,
            sourceType: $this->sourceType,
            sourceId: $this->sourceId,
            titleKey: $this->titleKey,
            messageKey: $this->messageKey,
            rendered: $this->rendered,
            dedupKey: $this->dedupKey,
            occurrences: $changes['occurrences'] ?? $this->occurrences,
            firstOccurredAt: $changes['firstOccurredAt'] ?? $this->firstOccurredAt,
            latestOccurredAt: $changes['latestOccurredAt'] ?? $this->latestOccurredAt,
            expiresAt: array_key_exists('expiresAt', $changes) ? $changes['expiresAt'] : $this->expiresAt,
            resolvedAt: array_key_exists('resolvedAt', $changes) ? $changes['resolvedAt'] : $this->resolvedAt,
            resolutionReason: array_key_exists('resolutionReason', $changes) ? $changes['resolutionReason'] : $this->resolutionReason,
            environment: $this->environment,
            actorId: $this->actorId,
            recipient: $this->recipient,
            action: $this->action,
            metadata: $this->metadata,
        );
    }

    /**
     * @param mixed $value
     * @return array{title:string,body:string}
     */
    private static function renderedFrom(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return ['title' => (string) ($value['title'] ?? ''), 'body' => (string) ($value['body'] ?? '')];
    }

    private static function dateFrom(mixed $value): DateTimeImmutable
    {
        return self::nullableDate($value) ?? new DateTimeImmutable('now');
    }

    private static function nullableDate(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return (DateTimeImmutable::createFromFormat(DATE_ATOM, $value) ?: null);
    }
}
