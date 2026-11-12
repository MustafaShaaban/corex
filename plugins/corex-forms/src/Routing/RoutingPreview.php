<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\Flow;
use InvalidArgumentException;

/**
 * Who "Routed owner" would be, for each rule and for a submission no rule matches (spec 122,
 * FR-019): the Emails tab's answer to "who gets this?" before anything is sent, by name and
 * address.
 *
 * A target that names nobody is followed as a submission follows it (FR-009): the flow's
 * fallback, then the flow's owner, with the reason each one failed. A target that depends on the
 * submission (the page's author, an answer, an extension) is said to depend on it; the others
 * are worked out as a submission would work them out.
 */
final readonly class RoutingPreview
{
    private const DEPENDS = ['page_owner', 'post_author', 'field_value', 'extension'];

    public function __construct(
        private AssigneeResolver $assignees,
        private RoutedRecipients $recipients,
        private RoutingOwnerLabel $labels,
    ) {
    }

    /**
     * @param array<string,mixed>       $routing The routing configuration on screen, saved or not.
     * @param list<array<string,mixed>> $schema
     *
     * @return list<array{label:string,target:string,people:list<array{id:int,name:string,email:string,reachable:bool}>,note:string}>
     */
    public function lines(Flow $flow, array $routing, array $schema): array
    {
        $fallback = (array) ($routing['fallback'] ?? []);
        $targets = [];
        foreach ((array) ($routing['rules'] ?? []) as $index => $rule) {
            if (is_array($rule) && ($rule['enabled'] ?? true)) {
                /* translators: %d: the position of a routing rule in the list. */
                $targets[sprintf(__('Rule %d', 'corex'), (int) $index + 1)] = [(array) ($rule['target'] ?? []), false];
            }
        }
        $targets[__('No rule matches', 'corex')] = [$fallback, true];

        $lines = [];
        foreach ($targets as $label => [$target, $isFallback]) {
            $lines[] = [
                'label' => (string) $label,
                'target' => $this->labels->describe($target, $flow, $schema),
                ...$this->reach($target, $fallback, $isFallback, $flow, $routing),
            ];
        }

        return $lines;
    }

    /**
     * Who the first target of the submission's chain ({@see RoutingChain}) that names somebody
     * reaches: names and addresses, or why it cannot be said before a submission arrives.
     *
     * @param array<string,mixed> $target   The rule's target, or the fallback for no match.
     * @param array<string,mixed> $fallback
     * @param array<string,mixed> $routing
     *
     * @return array{people:list<array{id:int,name:string,email:string,reachable:bool}>,note:string}
     */
    private function reach(array $target, array $fallback, bool $isFallback, Flow $flow, array $routing): array
    {
        $reasons = [];
        try {
            $chain = RoutingChain::for(self::target($target), self::target($fallback), $isFallback);
        } catch (InvalidArgumentException) {
            return ['people' => [], 'note' => __('This target is not complete yet.', 'corex')];
        }
        foreach ($chain as $candidate) {
            if (in_array($candidate->type, self::DEPENDS, true)) {
                return ['people' => [], 'note' => self::note($reasons, __('Worked out for each submission.', 'corex'))];
            }
            try {
                $resolution = $this->assignees->resolve($candidate, new RoutingSubject($flow, []));
            } catch (InvalidArgumentException) {
                return ['people' => [], 'note' => self::note($reasons, __('This target is not complete yet.', 'corex'))];
            }
            if ($resolution->assignee !== null) {
                return $this->people($resolution->assignee, $routing, $reasons);
            }
            $reasons[] = $resolution->reason;
        }

        return ['people' => [], 'note' => self::note(
            $reasons,
            __('Nobody could be assigned; it is in the inbox for whoever manages submissions.', 'corex'),
        )];
    }

    /**
     * @param array<string,mixed> $routing
     * @param list<string>        $reasons Why the targets before this one named nobody.
     *
     * @return array{people:list<array{id:int,name:string,email:string,reachable:bool}>,note:string}
     */
    private function people(Assignee $assignee, array $routing, array $reasons): array
    {
        $instead = $reasons === []
            ? ''
            /* translators: %s: the person, team or role the submission was assigned to instead. */
            : self::note($reasons, sprintf(__('Assigned to %s instead.', 'corex'), $this->assignees->name($assignee)));
        $routed = $this->recipients->for($assignee, $routing);
        if ($routed['note'] !== '') {
            return ['people' => [], 'note' => self::note($instead === '' ? [] : [$instead], $routed['note'])];
        }
        $limit = RoutedRecipients::limit($routing);

        return [
            'people' => array_map(
                static fn (RoutingPerson $person): array => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'email' => $person->email,
                    'reachable' => $person->email !== '',
                ],
                array_slice($this->recipients->people($assignee, $limit), 0, $limit),
            ),
            'note' => $instead,
        ];
    }

    /** @param array<string,mixed> $definition */
    private static function target(array $definition): RoutingTarget
    {
        return new RoutingTarget((string) ($definition['type'] ?? ''), (array) ($definition['config'] ?? []));
    }

    /** @param list<string> $before */
    private static function note(array $before, string $last): string
    {
        return trim(implode(' ', [...array_filter($before), $last]));
    }
}
