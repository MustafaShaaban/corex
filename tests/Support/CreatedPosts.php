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

    /** The site that was current when the watch began. On one site this never changes. */
    private readonly int $siteId;

    /** @param list<string>|null $postTypes Null for every type. */
    private function __construct(private readonly ?array $postTypes)
    {
        $this->siteId = get_current_blog_id();
    }

    public static function watch(string ...$postTypes): self
    {
        return self::listen(new self(array_values($postTypes)));
    }

    /**
     * Every post, of any type: for the check that a test left nothing behind.
     *
     * A test names the types it writes ({@see watch()}), and what it does not know it writes it
     * does not name. The integration test case watches for all of them around every test, and
     * fails the one that leaves any ({@see \Corex\Tests\Integration\TestCase}).
     */
    public static function watchAll(): self
    {
        return self::listen(new self(null));
    }

    private static function listen(self $watcher): self
    {
        add_action('wp_insert_post', [$watcher, 'remember'], 10, 3);
        // WordPress announces a new attachment by this action and not by `wp_insert_post`: a
        // watcher asked for attachments heard of none, and deleted none.
        add_action('add_attachment', [$watcher, 'rememberAttachment']);

        return $watcher;
    }

    /** The `add_attachment` listener. */
    public function rememberAttachment(int $postId): void
    {
        if ($this->watches('attachment')) {
            $this->ids[] = $postId;
        }
    }

    /** The `wp_insert_post` listener. An update is not a row this test created. */
    public function remember(int $postId, WP_Post $post, bool $update): void
    {
        if (! $update && $this->watches($post->post_type)) {
            $this->ids[] = $postId;
        }
    }

    /**
     * A post inserted while another site of a network is current is not remembered: its id is
     * another post's here, and that one is not this test's to delete.
     */
    private function watches(string $postType): bool
    {
        return get_current_blog_id() === $this->siteId
            && ($this->postTypes === null || in_array($postType, $this->postTypes, true));
    }

    /** @return list<int> */
    public function ids(): array
    {
        return $this->ids;
    }

    /**
     * The remembered posts that are still stored, each as `#12 page "Its title"`.
     *
     * Read from the table and not through `get_post()`, which answers from the object cache: a
     * row a test removed with a query of its own would still be reported.
     *
     * @return list<string>
     */
    public function stillStored(): array
    {
        global $wpdb;

        if ($this->ids === []) {
            return [];
        }

        $stored = $wpdb->get_results(
            "SELECT ID, post_type, post_title FROM {$wpdb->posts} WHERE ID IN ("
            . implode(',', array_map('intval', $this->ids)) . ') ORDER BY ID',
        );

        return array_map(
            static fn (object $post): string => sprintf('#%d %s "%s"', $post->ID, $post->post_type, $post->post_title),
            is_array($stored) ? $stored : [],
        );
    }

    /** Stop watching and permanently delete everything remembered, an attachment with its file. */
    public function delete(): void
    {
        remove_action('wp_insert_post', [$this, 'remember'], 10);
        remove_action('add_attachment', [$this, 'rememberAttachment']);

        foreach ($this->ids as $postId) {
            wp_delete_post($postId, true);
        }

        $this->ids = [];
    }
}
