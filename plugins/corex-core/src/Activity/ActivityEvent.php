<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Activity;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable, append-only product activity record shared by every CoreX module.
 */
final class ActivityEvent
{
    public const ACTOR_USER   = 'user';
    public const ACTOR_SYSTEM = 'system';
    public const ACTOR_CLI    = 'cli';
    public const ACTOR_CRON   = 'cron';

    public const AREA_OVERVIEW    = 'overview';
    public const AREA_ADDONS      = 'addons';
    public const AREA_FORMS       = 'forms';
    public const AREA_SUBMISSIONS = 'submissions';
    public const AREA_DATA        = 'data';
    public const AREA_DATA_MODELS = 'data-models';
    public const AREA_EMAIL       = 'email';
    public const AREA_BLOG        = 'blog';
    public const AREA_INSIGHTS    = 'insights';
    public const AREA_SETUP       = 'setup';
    public const AREA_SETTINGS    = 'settings';
    public const AREA_OPERATIONS  = 'operations';
    public const AREA_SECURITY    = 'security';
    public const AREA_NOTIFICATIONS = 'notifications';
    public const AREA_ACCESS      = 'access';
    public const AREA_THEME       = 'theme';
    public const AREA_DOCS        = 'docs';

    public const OUTCOME_SUCCESS  = 'success';
    public const OUTCOME_WARNING  = 'warning';
    public const OUTCOME_FAILURE  = 'failure';
    public const OUTCOME_DENIED   = 'denied';
    public const OUTCOME_CAPTURED = 'captured';
    public const OUTCOME_QUEUED   = 'queued';
    public const OUTCOME_SENT     = 'sent';
    public const OUTCOME_REVERTED = 'reverted';

    public const SENSITIVITY_PUBLIC_ADMIN = 'public-admin';
    public const SENSITIVITY_RESTRICTED   = 'restricted';
    public const SENSITIVITY_PERSONAL     = 'personal';
    public const SENSITIVITY_SECURITY     = 'security';

    private const ACTOR_KINDS = [self::ACTOR_USER, self::ACTOR_SYSTEM, self::ACTOR_CLI, self::ACTOR_CRON];

    private const AREAS = [
        self::AREA_OVERVIEW,
        self::AREA_ADDONS,
        self::AREA_FORMS,
        self::AREA_SUBMISSIONS,
        self::AREA_DATA,
        self::AREA_DATA_MODELS,
        self::AREA_EMAIL,
        self::AREA_BLOG,
        self::AREA_INSIGHTS,
        self::AREA_SETUP,
        self::AREA_SETTINGS,
        self::AREA_OPERATIONS,
        self::AREA_SECURITY,
        self::AREA_ACCESS,
        self::AREA_THEME,
        self::AREA_DOCS,
    ];

    private const OUTCOMES = [
        self::OUTCOME_SUCCESS,
        self::OUTCOME_WARNING,
        self::OUTCOME_FAILURE,
        self::OUTCOME_DENIED,
        self::OUTCOME_CAPTURED,
        self::OUTCOME_QUEUED,
        self::OUTCOME_SENT,
        self::OUTCOME_REVERTED,
    ];

    private const SENSITIVITIES = [
        self::SENSITIVITY_PUBLIC_ADMIN,
        self::SENSITIVITY_RESTRICTED,
        self::SENSITIVITY_PERSONAL,
        self::SENSITIVITY_SECURITY,
    ];

    /**
     * @param array{key:string,args:array<string,mixed>} $summary
     * @param array<string,mixed>                        $context
     */
    public function __construct(
        public readonly int $id,
        public readonly string $eventUuid,
        public readonly DateTimeImmutable $occurredAt,
        public readonly int $actorId,
        public readonly string $actorKind,
        public readonly string $actorLabel,
        public readonly string $area,
        public readonly string $kind,
        public readonly string $targetType,
        public readonly string $targetId,
        public readonly string $targetLabel,
        public readonly string $outcome,
        public readonly array $summary,
        public readonly array $context,
        public readonly string $sensitivity,
        public readonly DateTimeImmutable $retentionUntil,
    ) {
        $this->validate();
    }

    public function withId(int $id): self
    {
        return new self(
            id: $id,
            eventUuid: $this->eventUuid,
            occurredAt: $this->occurredAt,
            actorId: $this->actorId,
            actorKind: $this->actorKind,
            actorLabel: $this->actorLabel,
            area: $this->area,
            kind: $this->kind,
            targetType: $this->targetType,
            targetId: $this->targetId,
            targetLabel: $this->targetLabel,
            outcome: $this->outcome,
            summary: $this->summary,
            context: $this->context,
            sensitivity: $this->sensitivity,
            retentionUntil: $this->retentionUntil,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'event_uuid'      => $this->eventUuid,
            'occurred_at'     => $this->occurredAt->format(DATE_ATOM),
            'actor_id'        => $this->actorId,
            'actor_kind'      => $this->actorKind,
            'actor_label'     => $this->actorLabel,
            'area'            => $this->area,
            'kind'            => $this->kind,
            'target_type'     => $this->targetType,
            'target_id'       => $this->targetId,
            'target_label'    => $this->targetLabel,
            'outcome'         => $this->outcome,
            'summary'         => $this->summary,
            'context_json'    => $this->context,
            'sensitivity'     => $this->sensitivity,
            'retention_until' => $this->retentionUntil->format(DATE_ATOM),
        ];
    }

    private function validate(): void
    {
        if ($this->id < 0 || $this->actorId < 0) {
            throw new InvalidArgumentException('Activity identifiers cannot be negative.');
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->eventUuid) !== 1) {
            throw new InvalidArgumentException('Activity event UUID must be a valid version 4 UUID.');
        }

        $this->assertOneOf($this->actorKind, self::ACTOR_KINDS, 'actor kind');
        $this->assertOneOf($this->area, self::AREAS, 'area');
        $this->assertOneOf($this->outcome, self::OUTCOMES, 'outcome');
        $this->assertOneOf($this->sensitivity, self::SENSITIVITIES, 'sensitivity');

        if ($this->actorLabel === '' || $this->targetType === '' || $this->targetLabel === '') {
            throw new InvalidArgumentException('Activity actor and target labels cannot be empty.');
        }

        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $this->kind) !== 1) {
            throw new InvalidArgumentException('Activity kind must be a lowercase registered identifier.');
        }

        if (($this->summary['key'] ?? '') === '' || ! isset($this->summary['args']) || ! is_array($this->summary['args'])) {
            throw new InvalidArgumentException('Activity summary requires a translation key and argument map.');
        }

        $this->assertNoSecretKeys($this->summary['args']);
        $this->assertNoSecretKeys($this->context);
    }

    /** @param list<string> $allowed */
    private function assertOneOf(string $value, array $allowed, string $field): void
    {
        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported activity %s.', $field));
        }
    }

    /** @param array<mixed> $values */
    private function assertNoSecretKeys(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match('/(?:password|passphrase|secret|token|api[_-]?key|authorization|cookie|private[_-]?key)/i', $key) === 1) {
                throw new InvalidArgumentException('Activity context contains a secret-bearing key.');
            }

            if (is_array($value)) {
                $this->assertNoSecretKeys($value);
            }
        }
    }
}
