<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

/**
 * Who "Routed owner" mails for a submission (spec 122, US-FLW-04; DECISIONS #475).
 *
 * A user, their address; a team, every member's; a role, every holder's up to the flow's limit.
 * With nobody to mail, or a role over the limit, the flow's fallback recipient, else the site
 * administrator, and a note that the delivery record and the timeline show.
 */
final readonly class RoutedRecipients
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 100;

    public function __construct(private RoutingDirectory $directory)
    {
    }

    /**
     * @param array<string,mixed> $routing The flow version's routing configuration.
     *
     * @return array{addresses:list<string>,note:string}
     */
    public function for(Assignee $assignee, array $routing): array
    {
        $limit = self::limit($routing);
        $people = $this->people($assignee, $limit);
        if ($assignee->type === Assignee::ROLE && count($people) > $limit) {
            $fallback = $this->fallback($routing);

            return [
                'addresses' => [$fallback],
                'note' => sprintf(
                    /* translators: 1: the most people a role may reach, 2: the address the email went to instead. */
                    _n(
                        'Routed owner is a role with more than %1$d person; sent to %2$s',
                        'Routed owner is a role with more than %1$d people; sent to %2$s',
                        $limit,
                        'corex',
                    ),
                    $limit,
                    $fallback,
                ),
            ];
        }
        $addresses = array_values(array_unique(array_filter(array_map(
            static fn (RoutingPerson $person): string => $person->email,
            $people,
        ))));
        if ($addresses !== []) {
            return ['addresses' => $addresses, 'note' => ''];
        }
        $fallback = $this->fallback($routing);

        return [
            'addresses' => $fallback === '' ? [] : [$fallback],
            /* translators: %s: the address the email went to instead. */
            'note' => sprintf(__('Routed owner had no address; sent to %s', 'corex'), $fallback),
        ];
    }

    /**
     * The people an assignee reaches; for a role, one more than the limit, so going over it shows.
     *
     * @return list<RoutingPerson>
     */
    public function people(Assignee $assignee, int $limit): array
    {
        return match ($assignee->type) {
            Assignee::USER => array_values(array_filter([$this->directory->person((int) $assignee->key)])),
            Assignee::TEAM => array_values(array_filter(array_map(
                $this->directory->person(...),
                $this->directory->team($assignee->key)?->members ?? [],
            ))),
            Assignee::ROLE => $this->directory->peopleWithRole($assignee->key, $limit + 1),
            default => [],
        };
    }

    /** @param array<string,mixed> $routing */
    public static function limit(array $routing): int
    {
        $limit = (int) ($routing['recipient_limit'] ?? self::DEFAULT_LIMIT);

        return $limit < 1 ? self::DEFAULT_LIMIT : min($limit, self::MAX_LIMIT);
    }

    /** @param array<string,mixed> $routing */
    private function fallback(array $routing): string
    {
        $configured = trim((string) ($routing['fallback_recipient'] ?? ''));

        return is_email($configured) ? $configured : $this->directory->adminEmail();
    }
}
