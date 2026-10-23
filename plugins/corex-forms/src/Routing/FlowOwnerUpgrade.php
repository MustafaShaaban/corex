<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Submission\SubmissionRepository;

/**
 * Gives every flow submission that was assigned to nobody to its flow's owner, once (spec 122,
 * FR-014; DECISIONS #474).
 *
 * Before this release routing stored the route's own type ("flow_owner", "page_owner"…) as the
 * owner, which named nobody the inbox could show it to. Nothing is deleted. A submission whose
 * flow is gone, or whose owner has no account any more, is left as it is. The count is kept, and
 * the Forms & Flows screen says it.
 */
final readonly class FlowOwnerUpgrade
{
    public const OPTION = 'corex_flow_owner_upgrade';

    public function __construct(
        private SubmissionRepository $submissions,
        private FlowRepository $flows,
        private RoutingDirectory $directory,
    ) {
    }

    public function register(): void
    {
        add_action('init', [$this, 'runOnce'], 30);
    }

    public function runOnce(): void
    {
        // Autoloaded, so on every request after the first this is a read from memory. Claimed
        // before it runs, so two requests arriving together do not both move the same rows.
        if (get_option(self::OPTION) !== false || ! add_option(self::OPTION, ['ran_at' => gmdate(DATE_ATOM), 'moved' => 0], '', true)) {
            return;
        }
        update_option(self::OPTION, ['ran_at' => gmdate(DATE_ATOM), 'moved' => $this->run()], true);
    }

    public function run(): int
    {
        $moved = 0;
        $owners = [];
        foreach ($this->submissions->withUnresolvedOwner() as $submissionId) {
            $flowId = $this->submissions->flowOf($submissionId);
            $owners[$flowId] ??= $this->ownerOf($flowId);
            if ($owners[$flowId] === null) {
                continue;
            }
            $this->submissions->reassignToUser(
                $submissionId,
                $owners[$flowId]->id,
                /* translators: %s: the name of the person who owns the form. */
                sprintf(__('Assigned to %s, the form owner: it was assigned to nobody before this update.', 'corex'), $owners[$flowId]->name),
            );
            $moved++;
        }

        return $moved;
    }

    private function ownerOf(int $flowId): ?RoutingPerson
    {
        $flow = $flowId > 0 ? $this->flows->find($flowId) : null;

        return $flow === null ? null : $this->directory->person($flow->ownerId);
    }
}
