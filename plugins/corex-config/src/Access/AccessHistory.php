<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AbilityNames;
use Corex\Access\AccessPolicy;
use Corex\Access\AccessUserDirectory;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\ScreenAbilities;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;

/**
 * One history of who was given, refused or lost CoreX access, and who decided (spec 117, US-ACC-04).
 *
 * It reads the two places access events are kept, and keeps reading them there: the activity stream's
 * access area (role changes and refused ones, requests and their decisions, grants that ended) and the
 * access audit log's counted refusals (screens, Data screen records, routes and saves). Each becomes an
 * entry with a kind, who did it, to whom or what, when, and a sentence, newest first.
 */
final class AccessHistory
{
    public const PER_PAGE = 25;

    /** How long each source keeps its entries, in days, for the empty state. */
    public const ACTIVITY_DAYS = 180;
    public const REFUSAL_DAYS  = 30;

    /** The activity stream answers at most 100 a page; ten pages are the newest thousand events. */
    private const ACTIVITY_PAGE  = 100;
    private const ACTIVITY_PAGES = 10;

    private const KINDS = [
        'access.role.changed'      => 'role_changed',
        'access.role.refused'      => 'role_refused',
        'access.request.created'   => 'requested',
        'access.request.approved'  => 'approved',
        'access.request.denied'    => 'denied',
        'access.request.cancelled' => 'cancelled',
        'access.request.expired'   => 'request_expired',
        'access.grant.revoked'     => 'grant_revoked',
        'access.grant.expired'     => 'grant_expired',
    ];

    public function __construct(
        private readonly ActivityService $activity,
        private readonly AccessAuditLog $refusals,
        private readonly AccessUserDirectory $users,
        private readonly CorexAbilityCatalog $catalog,
        private readonly AccessService $access,
    ) {
    }

    /**
     * The kinds an entry can have, by key, in words, in the order the filter offers them.
     *
     * @return array<string,string>
     */
    public function kinds(): array
    {
        return [
            'role_changed'    => __('Role changed', 'corex'),
            'role_refused'    => __('Role change refused', 'corex'),
            'requested'       => __('Access requested', 'corex'),
            'approved'        => __('Request approved', 'corex'),
            'denied'          => __('Request denied', 'corex'),
            'cancelled'       => __('Request cancelled', 'corex'),
            'request_expired' => __('Request expired', 'corex'),
            'grant_revoked'   => __('Grant revoked', 'corex'),
            'grant_expired'   => __('Grant expired', 'corex'),
            'refused'         => __('Refused', 'corex'),
        ];
    }

    /**
     * One page of entries, newest first, with the people who appear anywhere in the history.
     *
     * @return array{
     *   entries:list<array{at:int,first:int,count:int,kind:string,kindLabel:string,who:string,whoId:int,subjectId:int,text:string}>,
     *   total:int,
     *   page:int,
     *   pages:int,
     *   people:array<int,string>
     * }
     */
    public function page(string $kind = '', int $personId = 0, int $page = 1): array
    {
        $all    = $this->all();
        $people = [];
        foreach ($all as $entry) {
            foreach ([$entry['whoId'], $entry['subjectId']] as $id) {
                if ($id > 0 && ! isset($people[$id])) {
                    $people[$id] = $this->users->displayName($id);
                }
            }
        }
        asort($people);

        $matching = array_values(array_filter(
            $all,
            static fn (array $entry): bool => ($kind === '' || $entry['kind'] === $kind)
                && ($personId < 1 || $entry['whoId'] === $personId || $entry['subjectId'] === $personId),
        ));
        $pages = max(1, (int) ceil(count($matching) / self::PER_PAGE));
        $page  = min(max(1, $page), $pages);

        return [
            'entries' => array_slice($matching, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total'   => count($matching),
            'page'    => $page,
            'pages'   => $pages,
            'people'  => $people,
        ];
    }

    /** @return list<array{at:int,first:int,count:int,kind:string,kindLabel:string,who:string,whoId:int,subjectId:int,text:string}> */
    private function all(): array
    {
        $entries = array_merge(
            array_values(array_filter(array_map($this->fromActivity(...), $this->events()))),
            array_map($this->fromRefusal(...), $this->refusals->entries()),
        );
        usort($entries, static fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return $entries;
    }

    /** @return list<ActivityEvent> */
    private function events(): array
    {
        $events = [];
        for ($page = 1; $page <= self::ACTIVITY_PAGES; $page++) {
            $batch = $this->activity->query(['area' => ActivityEvent::AREA_ACCESS], $page, self::ACTIVITY_PAGE);
            array_push($events, ...$batch);

            if (count($batch) < self::ACTIVITY_PAGE) {
                break;
            }
        }

        return $events;
    }

    /** @return array{at:int,first:int,count:int,kind:string,kindLabel:string,who:string,whoId:int,subjectId:int,text:string}|null */
    private function fromActivity(ActivityEvent $event): ?array
    {
        $kind = self::KINDS[$event->kind] ?? null;
        if ($kind === null) {
            return null;
        }

        $context   = $event->context;
        $subjectId = (int) ($context['requester_id'] ?? $context['person_id'] ?? 0);
        if ($event->kind === 'access.request.created') {
            $subjectId = $event->actorId;
        }
        $at = $event->occurredAt->getTimestamp();

        return [
            'at'        => $at,
            'first'     => $at,
            'count'     => 1,
            'kind'      => $kind,
            'kindLabel' => $this->kinds()[$kind],
            'who'       => $event->actorKind === ActivityEvent::ACTOR_USER ? $event->actorLabel : __('CoreX', 'corex'),
            'whoId'     => $event->actorKind === ActivityEvent::ACTOR_USER ? $event->actorId : 0,
            'subjectId' => $subjectId,
            'text'      => $this->sentence($kind, $event, $subjectId),
        ];
    }

    private function sentence(string $kind, ActivityEvent $event, int $subjectId): string
    {
        $context   = $event->context;
        $person    = $subjectId > 0 ? $this->users->displayName($subjectId) : '';
        $abilities = $this->names((array) ($context['abilities'] ?? []));

        return match ($kind) {
            'role_changed' => sprintf(
                /* translators: 1: a role's name, 2: what changed, e.g. "Manage forms and flows: Allow". */
                __('%1$s: %2$s', 'corex'),
                $this->roleName($event->targetId),
                $this->changes((array) ($context['new_effects'] ?? [])),
            ),
            'role_refused' => sprintf(
                /* translators: 1: a role's name, 2: why the change was refused. */
                __('%1$s, not changed. %2$s', 'corex'),
                $this->roleName($event->targetId),
                implode(' ', array_map(
                    fn (array $blocker): string => $this->access->refusalReason($blocker),
                    array_values(array_filter((array) ($context['blockers'] ?? []), 'is_array')),
                )),
            ),
            'requested' => sprintf(
                /* translators: %s: what was asked for, e.g. "Manage forms and flows". */
                __('Asked for %s', 'corex'),
                $this->requested($context),
            ),
            'approved' => sprintf(
                /* translators: 1: a person's name, 2: the abilities granted. */
                __('Granted %1$s %2$s', 'corex'),
                $person,
                $abilities,
            ),
            'denied' => sprintf(
                /* translators: 1: a person's name, 2: the abilities asked for. */
                __('Refused %1$s %2$s', 'corex'),
                $person,
                $abilities,
            ),
            'cancelled' => sprintf(
                /* translators: 1: a person's name, 2: the abilities asked for. */
                __('%1$s’s request for %2$s was cancelled: an administrator grants it through the role matrix', 'corex'),
                $person,
                $abilities,
            ),
            'request_expired' => sprintf(
                /* translators: 1: a person's name, 2: the abilities asked for. */
                __('%1$s’s request for %2$s ran out before it was decided', 'corex'),
                $person,
                $abilities,
            ),
            'grant_revoked' => sprintf(
                /* translators: 1: a person's name, 2: the abilities taken back. */
                __('Took back %2$s from %1$s', 'corex'),
                $person,
                $abilities,
            ),
            default => sprintf(
                /* translators: 1: a person's name, 2: the abilities that ended. */
                __('%1$s’s %2$s ended on its expiry date', 'corex'),
                $person,
                $abilities,
            ),
        };
    }

    /**
     * @param array{user:int,kind:string,section:string,first:int,last:int,count:int} $entry
     *
     * @return array{at:int,first:int,count:int,kind:string,kindLabel:string,who:string,whoId:int,subjectId:int,text:string}
     */
    private function fromRefusal(array $entry): array
    {
        $who = $entry['user'] > 0 ? $this->users->displayName($entry['user']) : __('Signed-out visitor', 'corex');

        return [
            'at'        => $entry['last'],
            'first'     => $entry['first'],
            'count'     => $entry['count'],
            'kind'      => 'refused',
            'kindLabel' => $this->kinds()['refused'],
            'who'       => $who,
            'whoId'     => $entry['user'],
            'subjectId' => $entry['user'],
            'text'      => match ($entry['kind']) {
                /* translators: %s: the key of a data model, e.g. "table-access-requests". */
                'data'   => sprintf(__('Refused records of %s on the Data screen', 'corex'), $entry['section']),
                /* translators: %s: a CoreX screen's name. */
                'action' => sprintf(__('Refused an action on %s', 'corex'), ScreenAbilities::name($entry['section'])),
                /* translators: %s: a CoreX screen's name. */
                default  => sprintf(__('Refused %s', 'corex'), ScreenAbilities::name($entry['section'])),
            },
        ];
    }

    /** @param array<string,mixed> $context */
    private function requested(array $context): string
    {
        if (is_string($context['ability_key'] ?? null)) {
            return $this->names([$context['ability_key']]);
        }

        return sprintf(
            /* translators: %s: the name of a CoreX area, e.g. "Forms & Flows". */
            __('the %s area', 'corex'),
            AbilityNames::area((string) ($context['area_key'] ?? '')),
        );
    }

    /** @param array<string,mixed> $effects ability key => effect */
    private function changes(array $effects): string
    {
        $words = [
            AccessPolicy::EFFECT_ALLOW   => __('Allow', 'corex'),
            AccessPolicy::EFFECT_DENY    => __('Deny', 'corex'),
            AccessPolicy::EFFECT_INHERIT => __('Not set', 'corex'),
        ];
        $lines = [];
        foreach ($effects as $key => $effect) {
            $lines[] = sprintf(
                /* translators: 1: the name of a CoreX ability, 2: Allow, Deny or Not set. */
                __('%1$s set to %2$s', 'corex'),
                $this->names([(string) $key]),
                $words[$effect] ?? (string) $effect,
            );
        }

        return implode('; ', $lines);
    }

    /** @param list<mixed> $keys */
    private function names(array $keys): string
    {
        return implode(', ', array_map(function (mixed $key): string {
            $ability = $this->catalog->find((string) $key);

            return '“' . ($ability !== null ? AbilityNames::labelOf($ability) : (string) $key) . '”';
        }, $keys));
    }

    private function roleName(string $roleKey): string
    {
        $names = function_exists('wp_roles') ? wp_roles()->get_names() : [];

        return isset($names[$roleKey]) ? translate_user_role((string) $names[$roleKey]) : $roleKey;
    }

}
