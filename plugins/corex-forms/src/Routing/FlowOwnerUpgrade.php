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
 * flow is gone, or whose owner has no account any more, is left as it is.
 *
 * It moves one batch per run, from a signed-in admin request or from cron, never from a visitor's
 * request. Each batch is written down when it is done, and the upgrade is recorded as finished,
 * with the count the Forms & Flows screen says, only after the last one. A run that dies midway
 * leaves the record as it was and the next run starts again from it: a submission already moved
 * names a user and is not picked again.
 */
final readonly class FlowOwnerUpgrade
{
    public const OPTION = 'corex_flow_owner_upgrade';
    public const CRON_HOOK = 'corex_flow_owner_upgrade';
    public const BATCH_SIZE = 200;

    /** Named for the site by {@see SubmissionRepository::unlessBusy()}. */
    private const LOCK = 'corex_flow_owner_upgrade';

    public function __construct(
        private SubmissionRepository $submissions,
        private FlowRepository $flows,
        private RoutingDirectory $directory,
        private int $batchSize = self::BATCH_SIZE,
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'stepFromAdmin']);
        add_action(self::CRON_HOOK, [$this, 'step']);
    }

    /** `admin_init` also fires for admin-ajax.php and admin-post.php, which a visitor can reach. */
    public function stepFromAdmin(): void
    {
        if (wp_doing_ajax() || ! is_user_logged_in()) {
            return;
        }
        $this->step();
    }

    /**
     * Moves one batch.
     *
     * @return bool Whether the upgrade has finished.
     */
    public function step(): bool
    {
        // Autoloaded, so once it is done this is a read from memory on every admin request.
        if (self::finished(get_option(self::OPTION))) {
            return true;
        }
        $done = false;
        $ran = $this->submissions->unlessBusy(self::LOCK, function () use (&$done): void {
            $done = $this->batch();
        });
        if (! $ran) {
            // Another request is moving a batch. Come back after it, from cron if nobody opens
            // the admin: a cron run that found the lock busy must not be the last one.
            $this->scheduleNext(MINUTE_IN_SECONDS);
        }

        return $done;
    }

    /** Runs under the lock, so no two requests move the same batch. */
    private function batch(): bool
    {
        // Read again under the lock: another request may have moved a batch since this one began.
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete(self::OPTION, 'options');
        $record = get_option(self::OPTION);
        if (self::finished($record)) {
            return true;
        }
        $after = max(0, (int) ($record['after'] ?? 0));
        $moved = max(0, (int) ($record['moved'] ?? 0));
        $batch = $this->submissions->withUnresolvedOwner($after, $this->batchSize);
        $moved += $this->move($batch);

        if (count($batch) < $this->batchSize) {
            update_option(self::OPTION, ['ran_at' => gmdate(DATE_ATOM), 'moved' => $moved], true);
            wp_clear_scheduled_hook(self::CRON_HOOK);

            return true;
        }
        update_option(self::OPTION, ['after' => (int) end($batch), 'moved' => $moved], true);
        // The rest goes on in the background, whether or not anyone opens the admin again.
        $this->scheduleNext(0);

        return false;
    }

    private function scheduleNext(int $inSeconds): void
    {
        if (wp_next_scheduled(self::CRON_HOOK) === false) {
            wp_schedule_single_event(time() + $inSeconds, self::CRON_HOOK);
        }
    }

    /** @param list<int> $submissionIds */
    private function move(array $submissionIds): int
    {
        $moved = 0;
        $owners = [];
        foreach ($submissionIds as $submissionId) {
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

    private static function finished(mixed $record): bool
    {
        return is_array($record) && isset($record['ran_at']);
    }
}
