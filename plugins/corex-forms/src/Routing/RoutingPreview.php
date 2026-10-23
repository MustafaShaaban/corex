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
     * @return list<array{label:string,target:string,people:list<array{id:int,name:string,email:string}>,note:string}>
     */
    public function lines(Flow $flow, array $routing, array $schema): array
    {
        $lines = [];
        foreach ((array) ($routing['rules'] ?? []) as $index => $rule) {
            if (! is_array($rule) || ! ($rule['enabled'] ?? true)) {
                continue;
            }
            $lines[] = $this->line(
                /* translators: %d: the position of a routing rule in the list. */
                sprintf(__('Rule %d', 'corex'), (int) $index + 1),
                (array) ($rule['target'] ?? []),
                $flow,
                $routing,
                $schema,
            );
        }
        $lines[] = $this->line(__('No rule matches', 'corex'), (array) ($routing['fallback'] ?? []), $flow, $routing, $schema);

        return $lines;
    }

    /**
     * @param array<string,mixed>       $target
     * @param array<string,mixed>       $routing
     * @param list<array<string,mixed>> $schema
     *
     * @return array{label:string,target:string,people:list<array{id:int,name:string,email:string}>,note:string}
     */
    private function line(string $label, array $target, Flow $flow, array $routing, array $schema): array
    {
        $line = ['label' => $label, 'target' => $this->labels->describe($target, $flow, $schema), 'people' => [], 'note' => ''];
        $type = (string) ($target['type'] ?? '');
        if (in_array($type, self::DEPENDS, true)) {
            return [...$line, 'note' => __('Worked out for each submission.', 'corex')];
        }
        try {
            $resolution = $this->assignees->resolve(
                new RoutingTarget($type, (array) ($target['config'] ?? [])),
                new RoutingSubject($flow, []),
            );
        } catch (InvalidArgumentException) {
            return [...$line, 'note' => __('This target is not complete yet.', 'corex')];
        }
        if ($resolution->assignee === null) {
            return [...$line, 'note' => $resolution->reason];
        }
        $routed = $this->recipients->for($resolution->assignee, $routing);
        $people = array_map(
            static fn (RoutingPerson $person): array => $person->toArray(),
            array_slice($this->recipients->people($resolution->assignee, RoutedRecipients::limit($routing)), 0, RoutedRecipients::limit($routing)),
        );

        return [...$line, 'people' => $routed['note'] === '' ? $people : [], 'note' => $routed['note']];
    }
}
