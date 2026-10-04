<?php

/**
 * Remembers the posts a test causes to be inserted, so the test can delete exactly those.
 *
 * The integration suite runs against a developer's real install, and much of what a test writes
 * there is written by a listener that hands back no id: a logged email, a stored submission, an
 * import run. Comparing the install before and after ("the newest 500 ids") is not a substitute.
 * It cannot see a row dated before the 500th, and it deletes whatever anything else inserted in
 * the meantime — other sessions run this suite against the same database. The `wp_insert_post`
 * action fires only for this process's own inserts.
 *
 *     beforeEach(fn () => $this->emailLogs = CreatedPosts::watch('corex_email_log'));
 *     afterEach(fn () => $this->emailLogs->delete());
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use WP_Post;

final class CreatedPosts
{
    /** @var list<int> */
    private array $ids = [];

    /** @param list<string> $postTypes */
    private function __construct(private readonly array $postTypes)
    {
    }

    public static function watch(string ...$postTypes): self
    {
        $watcher = new self(array_values($postTypes));
        add_action('wp_insert_post', [$watcher, 'remember'], 10, 3);

        return $watcher;
    }

    /** The `wp_insert_post` listener. An update is not a row this test created. */
    public function remember(int $postId, WP_Post $post, bool $update): void
    {
        if (! $update && in_array($post->post_type, $this->postTypes, true)) {
            $this->ids[] = $postId;
        }
    }

    /** @return list<int> */
    public function ids(): array
    {
        return $this->ids;
    }

    /** Stop watching and permanently delete everything remembered. */
    public function delete(): void
    {
        remove_action('wp_insert_post', [$this, 'remember'], 10);

        foreach ($this->ids as $postId) {
            wp_delete_post($postId, true);
        }

        $this->ids = [];
    }
}
