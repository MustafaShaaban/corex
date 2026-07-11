<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Routing;

defined('ABSPATH') || exit;

use Corex\Email\Studio\EmailStudioStore;
use DateTimeImmutable;
use DomainException;

/**
 * Persistent trigger routing configuration.
 */
final class EmailRouteRepository
{
    private const TYPE = 'email_route';

    public function __construct(private readonly EmailStudioStore $store)
    {
    }

    public function save(EmailRoute $route): EmailRoute
    {
        $payload = [
            'template_id'     => $route->templateId,
            'flow_id'         => $route->flowId,
            'template_version_policy' => $route->templateVersionPolicy,
            'recipient_rules' => $route->recipientRules,
            'reply_to_rule'   => $route->replyToRule,
            'enabled'         => $route->enabled,
            'priority'        => $route->priority,
            'updated_by'      => $route->updatedBy,
            'updated_at'      => $route->updatedAt->format(DATE_ATOM),
        ];
        $existing = $this->store->findBySlug(self::TYPE, $route->trigger);
        if ($existing === null) {
            $id = $this->store->create(self::TYPE, $route->trigger, $route->trigger, 0, $payload);
        } else {
            $id = $existing['id'];
            if (! $this->store->update($id, $route->trigger, $payload)) {
                throw new DomainException(__('Email route could not be updated.', 'corex'));
            }
        }

        $record = $this->store->find($id);

        return $this->route($record ?? throw new DomainException(__('Email route could not be stored.', 'corex')));
    }

    public function findByTrigger(string $trigger): ?EmailRoute
    {
        $record = $this->store->findBySlug(self::TYPE, $trigger);

        return $record === null ? null : $this->route($record);
    }

    /** @return list<EmailRoute> */
    public function all(): array
    {
        return array_map($this->route(...), $this->store->all(self::TYPE));
    }

    /** @param array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} $record */
    private function route(array $record): EmailRoute
    {
        $payload = $record['payload'];

        return new EmailRoute(
            id: $record['id'],
            trigger: $record['slug'],
            templateId: (int) ($payload['template_id'] ?? 0),
            recipientRules: $this->rules($payload['recipient_rules'] ?? []),
            replyToRule: $this->rule($payload['reply_to_rule'] ?? null),
            enabled: (bool) ($payload['enabled'] ?? false),
            updatedBy: (int) ($payload['updated_by'] ?? 0),
            updatedAt: new DateTimeImmutable((string) ($payload['updated_at'] ?? '')),
            flowId: is_int($payload['flow_id'] ?? null) ? $payload['flow_id'] : null,
            templateVersionPolicy: (string) ($payload['template_version_policy'] ?? 'active'),
            priority: (int) ($payload['priority'] ?? 100),
        );
    }

    /** @return list<array{source:string,path?:string,value?:string}> */
    private function rules(mixed $rules): array
    {
        $normalized = [];
        foreach (is_array($rules) ? $rules : [] as $rule) {
            $valid = $this->rule($rule);
            if ($valid !== null) {
                $normalized[] = $valid;
            }
        }

        return $normalized;
    }

    /** @return array{source:string,path?:string,value?:string}|null */
    private function rule(mixed $rule): ?array
    {
        if (! is_array($rule) || ! is_string($rule['source'] ?? null)) {
            return null;
        }

        $normalized = ['source' => $rule['source']];
        if (is_string($rule['path'] ?? null)) {
            $normalized['path'] = $rule['path'];
        }
        if (is_string($rule['value'] ?? null)) {
            $normalized['value'] = $rule['value'];
        }

        return $normalized;
    }
}
