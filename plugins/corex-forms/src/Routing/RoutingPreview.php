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
 * FR-019): the Emails tab's answer to "who gets this?" before anything is sent.
 *
 * A target that depends on the submission (the page's author, an answer, an extension) is said
 * to depend on it; the others are worked out as a submission would work them out.
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
     * @return list<array{label:string,target:string,people:list<array{id:int,name:string,reachable:bool}>,note:string}>
     */
    public function lines(Flow $flow, array $routing, array $schema): array
    {
        $targets = [];
        foreach ((array) ($routing['rules'] ?? []) as $index => $rule) {
            if (is_array($rule) && ($rule['enabled'] ?? true)) {
                /* translators: %d: the position of a routing rule in the list. */
                $targets[sprintf(__('Rule %d', 'corex'), (int) $index + 1)] = (array) ($rule['target'] ?? []);
            }
        }
        $targets[__('No rule matches', 'corex')] = (array) ($routing['fallback'] ?? []);

        $lines = [];
        foreach ($targets as $label => $target) {
            $lines[] = [
                'label' => (string) $label,
                'target' => $this->labels->describe($target, $flow, $schema),
                ...$this->reach($target, $flow, $routing),
            ];
        }

        return $lines;
    }

    /**
     * Who one target reaches: names, or why it cannot be said before a submission arrives.
     *
     * @param array<string,mixed> $target
     * @param array<string,mixed> $routing
     *
     * @return array{people:list<array{id:int,name:string,reachable:bool}>,note:string}
     */
    private function reach(array $target, Flow $flow, array $routing): array
    {
        $type = (string) ($target['type'] ?? '');
        if (in_array($type, self::DEPENDS, true)) {
            return ['people' => [], 'note' => __('Worked out for each submission.', 'corex')];
        }
        try {
            $resolution = $this->assignees->resolve(
                new RoutingTarget($type, (array) ($target['config'] ?? [])),
                new RoutingSubject($flow, []),
            );
        } catch (InvalidArgumentException) {
            return ['people' => [], 'note' => __('This target is not complete yet.', 'corex')];
        }
        if ($resolution->assignee === null) {
            return ['people' => [], 'note' => $resolution->reason];
        }
        $routed = $this->recipients->for($resolution->assignee, $routing);
        if ($routed['note'] !== '') {
            return ['people' => [], 'note' => $routed['note']];
        }
        $limit = RoutedRecipients::limit($routing);

        // Names, and whether each has an address: whoever edits a form sees who gets its email,
        // not every user's address (spec 122, FR-019).
        return [
            'people' => array_map(
                static fn (RoutingPerson $person): array => ['id' => $person->id, 'name' => $person->name, 'reachable' => $person->email !== ''],
                array_slice($this->recipients->people($resolution->assignee, $limit), 0, $limit),
            ),
            'note' => '',
        ];
    }
}
