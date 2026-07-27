<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * What the editorial panel may offer from a given state (spec 075, FR-2).
 *
 * Worth stating plainly, because it is not what "editorial workflow" usually implies:
 * {@see EditorialWorkflowService::transition()} has **no transition graph**. It accepts any of the six
 * states from any other and maps each onto a native WordPress status. Its only real constraint is that
 * `scheduled` without a timestamp throws.
 *
 * So this offers every state but the one the post is already in. Building a graph here would be new
 * domain logic — spec 075 §10 forbids it, and a UI that implied a rule the service does not enforce
 * would be exactly the kind of confident-but-untrue surface Spec 074 existed to remove.
 */
final class EditorialTransitions
{
    /** @return list<string> */
    public static function states(): array
    {
        return BlogProLabels::editorialStates();
    }

    /**
     * The moves available from `$current`, each already carrying the word the panel renders.
     *
     * An unrecognised current state — one stored by an older version, or by a plugin — offers all six
     * rather than none. Offering none would strand the post with no way out of a state CoreX no longer
     * understands.
     *
     * @return list<array{key:string,label:string,requires_schedule:bool}>
     */
    public static function from(string $current): array
    {
        $options = [];

        foreach (self::states() as $state) {
            if ($state === $current) {
                continue;
            }

            $options[] = [
                'key'   => $state,
                'label' => BlogProLabels::editorialState($state),
                // The panel requires the field up front rather than letting the request fail at the
                // server with an exception the person cannot act on.
                'requires_schedule' => $state === EditorialItem::STATE_SCHEDULED,
            ];
        }

        return $options;
    }
}
