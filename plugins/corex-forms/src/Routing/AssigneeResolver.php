<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use Throwable;

/**
 * Turns a routing target into the user, team or role it assigns (spec 122, FR-008, FR-010, FR-011).
 *
 * Every failure is a sentence the submission's timeline shows, saying which target named nobody
 * and why. Targets the editor no longer offers (department, email) still read from stored flows and
 * name nobody, so their submissions take the fallback; "Post author" is read as "Page owner".
 */
final readonly class AssigneeResolver
{
    public function __construct(
        private RoutingDirectory $directory,
        private RoutingTargetRegistry $extensions,
    ) {
    }

    public function resolve(RoutingTarget $target, RoutingSubject $subject): TargetResolution
    {
        $value = $target->config['value'] ?? null;

        return match ($target->type) {
            'user' => $this->user((int) $value, __('The person this route names no longer has an account.', 'corex')),
            'team' => $this->team(is_scalar($value) ? (string) $value : ''),
            'role' => $this->role(is_scalar($value) ? (string) $value : ''),
            'flow_owner' => $this->user($subject->flow->ownerId, __("The form's owner no longer has an account.", 'corex')),
            'page_owner', 'post_author' => $this->pageOwner($subject->pageId),
            'field_value' => $this->fieldValue(is_scalar($value) ? (string) $value : '', (array) ($target->config['owners'] ?? []), $subject),
            'extension' => $this->extension(is_scalar($value) ? (string) $value : '', $subject),
            default => TargetResolution::failed(__('This route uses a target CoreX no longer offers.', 'corex')),
        };
    }

    /** The assignee as people read it: a person's name, a team's name, a role's name. */
    public function name(Assignee $assignee): string
    {
        return match ($assignee->type) {
            Assignee::USER => $this->directory->person((int) $assignee->key)?->name ?? '',
            Assignee::TEAM => $this->directory->team($assignee->key)?->name ?? $assignee->key,
            Assignee::ROLE => $this->directory->roleName($assignee->key) ?? $assignee->key,
            default => '',
        };
    }

    private function user(int $userId, string $reason): TargetResolution
    {
        return $this->directory->person($userId) !== null
            ? TargetResolution::found(Assignee::user($userId))
            : TargetResolution::failed($reason);
    }

    private function team(string $key): TargetResolution
    {
        return $key !== '' && $this->directory->team($key) !== null
            ? TargetResolution::found(Assignee::team($key))
            /* translators: %s: the key of a team that was removed. */
            : TargetResolution::failed(sprintf(__('The team "%s" no longer exists.', 'corex'), $key));
    }

    private function role(string $key): TargetResolution
    {
        return $key !== '' && $this->directory->roleName($key) !== null
            ? TargetResolution::found(Assignee::role($key))
            /* translators: %s: the key of a user role the site no longer has. */
            : TargetResolution::failed(sprintf(__('The role "%s" no longer exists.', 'corex'), $key));
    }

    private function pageOwner(?int $pageId): TargetResolution
    {
        if ($pageId === null) {
            return TargetResolution::failed(__('The page the form was sent from is not known.', 'corex'));
        }
        $author = $this->directory->pageAuthor($pageId);

        return $author !== null
            ? TargetResolution::found(Assignee::user($author))
            : TargetResolution::failed(__('The page the form was sent from has no author.', 'corex'));
    }

    /** @param list<mixed> $owners Rows of `['value' => answer, 'target' => ['type' => …, 'value' => …]]`. */
    private function fieldValue(string $field, array $owners, RoutingSubject $subject): TargetResolution
    {
        $answer = $subject->values[$field] ?? null;
        $picked = array_values(array_filter(array_map('strval', is_array($answer) ? $answer : [$answer]), static fn (string $value): bool => $value !== ''));
        if ($picked === []) {
            /* translators: %s: the key of the question whose answer chooses the owner. */
            return TargetResolution::failed(sprintf(__('The question "%s" was not answered.', 'corex'), $field));
        }
        $reasons = [];
        foreach ($picked as $value) {
            $row = $this->ownerRow($owners, $value);
            $owner = $row === null ? null : Assignee::from((array) ($row['target'] ?? []));
            if ($owner === null) {
                /* translators: 1: the answer the visitor picked, 2: the key of the question. */
                $reasons[] = sprintf(__('The answer "%1$s" to "%2$s" has no owner.', 'corex'), $value, $field);
                continue;
            }
            $resolution = $this->owner($owner);
            if ($resolution->assignee !== null) {
                return $resolution;
            }
            $reasons[] = $resolution->reason;
        }

        return TargetResolution::failed(implode(' ', $reasons));
    }

    /** @param list<mixed> $owners */
    private function ownerRow(array $owners, string $value): ?array
    {
        foreach ($owners as $row) {
            if (is_array($row) && (string) ($row['value'] ?? '') === $value) {
                return $row;
            }
        }

        return null;
    }

    private function owner(Assignee $owner): TargetResolution
    {
        return match ($owner->type) {
            Assignee::USER => $this->user((int) $owner->key, __('The person this answer names no longer has an account.', 'corex')),
            Assignee::TEAM => $this->team($owner->key),
            default => $this->role($owner->key),
        };
    }

    private function extension(string $key, RoutingSubject $subject): TargetResolution
    {
        if (! $this->extensions->has($key)) {
            /* translators: %s: the key of a routing extension. */
            return TargetResolution::failed(sprintf(__('The routing extension "%s" is not available.', 'corex'), $key));
        }
        try {
            $assignee = $this->extensions->resolve($key, $subject->toArray());
        } catch (Throwable $error) {
            /* translators: 1: the routing extension's name, 2: the error it raised. */
            return TargetResolution::failed(sprintf(__('The routing extension "%1$s" failed: %2$s', 'corex'), $this->extensions->label($key), $error->getMessage()));
        }
        if ($assignee === null) {
            /* translators: %s: the routing extension's name. */
            return TargetResolution::failed(sprintf(__('The routing extension "%s" named nobody.', 'corex'), $this->extensions->label($key)));
        }

        return $this->owner($assignee);
    }
}
