<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Stands between WordPress and a submission that is being trashed or deleted by something other
 * than the inbox (spec 105, FR-019 and FR-020).
 *
 * WordPress's daily trash clean-up deletes any trashed post it has a date for, and the retention
 * panel trashed submissions WordPress's way before the inbox had a trash. So a submission that
 * is trashed that way is taken onto CoreX's clock at once, and the clean-up is refused one it
 * still has a date for: a trashed submission goes when CoreX's clock says, and not before.
 *
 * Anything can also call `wp_delete_post()`: a command, another plugin. That cannot be refused
 * without breaking whoever asked, so what is tied to the submission is removed with it. A file
 * uploaded with it would otherwise stay on disk with nothing left that knows where it is.
 */
final readonly class SubmissionDeletionGuard
{
    private const POST_TYPE = 'corex_submission';

    /** Enough for what one request trashes; the daily sweep adopts whatever is left. */
    private const MOST_ADOPTED_AT_ONCE = 50;

    public function __construct(
        private SubmissionTrashService $trash,
        private SubmissionTrashStore $store,
    ) {
    }

    public function register(): void
    {
        add_action('trashed_post', [$this, 'adoptWhenTrashedByWordPress'], 10, 1);
        add_filter('pre_delete_post', [$this, 'keepFromWordPressCleanUp'], 10, 2);
        add_action('before_delete_post', [$this, 'forgetTiedData'], 10, 2);
    }

    public function adoptWhenTrashedByWordPress(int $postId): void
    {
        if (get_post_type($postId) === self::POST_TYPE) {
            $this->store->adoptWordPressTrash(self::MOST_ADOPTED_AT_ONCE);
        }
    }

    /**
     * @param mixed $delete What an earlier filter decided: null to go on deleting.
     *
     * @return mixed False to keep the post, or what was decided before.
     */
    public function keepFromWordPressCleanUp(mixed $delete, mixed $post): mixed
    {
        // WordPress runs its clean-up as the action of this name, from its daily schedule.
        return $this->isSubmission($post) && doing_action('wp_scheduled_delete') ? false : $delete;
    }

    public function forgetTiedData(int $postId, mixed $post): void
    {
        if ($this->isSubmission($post)) {
            $this->trash->forgetTiedData($postId);
        }
    }

    private function isSubmission(mixed $post): bool
    {
        return $post instanceof \WP_Post && $post->post_type === self::POST_TYPE;
    }
}
