<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

/**
 * A requested time follows its request in the Submissions inbox (spec 129, FR-021): offered again when the
 * request is moved to the trash or deleted, taken again when it is restored and the time is still free.
 *
 * The inbox trashes and restores by changing the post's status and deletes with `wp_delete_post()`, and
 * retention trashes and deletes through the same store, so the two WordPress hooks below see every way a
 * request leaves or comes back. A restored
 * request whose time another visitor has asked for since is restored all the same: two requests then name
 * the time, and the team sees both.
 */
final class SubmissionSlots
{
    /** The post type `Corex\Forms\Submission\SubmissionRepository` stores a submission as; named here, as {@see StoredCallbackSlot} explains. */
    public const POST_TYPE = 'corex_submission';

    /**
     * @param \Closure(int): ?int $slotOf the time a stored callback request asked for, null for any other submission
     * @param \Closure(): int     $now
     */
    public function __construct(
        private readonly SlotClaims $claims,
        private readonly \Closure $slotOf,
        private readonly \Closure $now,
    ) {
    }

    public function register(): void
    {
        add_action('transition_post_status', function (string $new, string $old, \WP_Post $post): void {
            $this->statusChanged((int) $post->ID, (string) $post->post_type, $new, $old);
        }, 10, 3);
        add_action('deleted_post', function (int $id, \WP_Post $post): void {
            $this->deleted($id, (string) $post->post_type);
        }, 10, 2);
    }

    public function statusChanged(int $id, string $postType, string $new, string $old): void
    {
        if ($postType !== self::POST_TYPE || $new === $old) {
            return;
        }
        if ($new === 'trash') {
            $this->claims->releaseFor($id);

            return;
        }
        if ($old === 'trash') {
            $this->claimAgain($id);
        }
    }

    public function deleted(int $id, string $postType): void
    {
        if ($postType === self::POST_TYPE) {
            $this->claims->releaseFor($id);
        }
    }

    private function claimAgain(int $id): void
    {
        $slot = ($this->slotOf)($id);
        if ($slot !== null && $slot > ($this->now)() && $this->claims->claim($slot)) {
            $this->claims->confirm($slot, $id);
        }
    }
}
