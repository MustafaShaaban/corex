<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use Corex\Admin\AdminPage;
use Corex\Security\Admin\AdminGuard;
use DateTimeImmutable;
use RuntimeException;

/**
 * Functional Blog Pro admin surface over native WordPress posts, comments, authors, and first-party analytics.
 */
final class BlogProScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly BlogProServices $services,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Blog Pro', 'corex'),
            __('Blog Pro', 'corex'),
            'manage_options',
            'corex-blog-pro',
            [$this, 'render'],
            33,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        wp_enqueue_style(
            'corex-blog-pro',
            plugins_url('assets/blog-pro.css', COREX_CONFIG_FILE),
            ['corex-admin-shell'],
            '1.0.0',
        );
        $base = dirname(__DIR__, 2);
        $asset = is_file($base . '/build/admin/index.asset.php')
            ? require $base . '/build/admin/index.asset.php'
            : ['dependencies' => [], 'version' => 'dev'];
        wp_enqueue_script(
            'corex-blog-pro',
            plugins_url('build/admin/index.js', $base . '/corex-config.php'),
            [...$asset['dependencies'], 'corex-runtime'],
            $asset['version'],
            true,
        );
        wp_localize_script('corex-blog-pro', 'corexBlogPro', $this->clientConfig());
        wp_set_script_translations('corex-blog-pro', 'corex');
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('blog-pro');

            return;
        }

        echo $this->page->open(
            'blog-pro',
            __('CoreX Blog Pro', 'corex'),
            __('Native publishing, analytics, comments, authors, and sharing in one real workflow.', 'corex'),
        );
        echo '<div id="corex-blog-pro-app" aria-live="polite"></div>';
        echo $this->page->close();
    }

    /**
     * @return array<string,mixed>
     */
    private function clientConfig(): array
    {
        $posts = $this->posts();
        $selectedPostId = (int) ($posts[0]['id'] ?? 0);

        return [
            'restUrl' => esc_url_raw(rest_url('corex/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'posts' => $posts,
            'selectedPostId' => $selectedPostId,
            'analytics' => $selectedPostId > 0 ? $this->analytics($selectedPostId) : [],
            'editorial' => $selectedPostId > 0 ? $this->editorial($selectedPostId) : null,
            'comments' => $selectedPostId > 0 ? array_map($this->comment(...), $this->services->comments->queue($selectedPostId)) : [],
            'authors' => $this->authors(),
            'shareControls' => $selectedPostId > 0 ? $this->shareControls($selectedPostId) : [],
        ];
    }

    /**
     * @return list<array{id:int,title:string,status:string,permalink:string}>
     */
    private function posts(): array
    {
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => ['draft', 'pending', 'future', 'publish'],
            'numberposts' => 20,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        return array_map(static fn (\WP_Post $post): array => [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'status' => (string) $post->post_status,
            'permalink' => get_permalink($post) ?: '',
        ], is_array($posts) ? $posts : []);
    }

    /**
     * @return array<string,mixed>
     */
    private function analytics(int $postId): array
    {
        $aggregate = $this->services->analytics->aggregate($postId, ...$this->range());

        return [
            'views' => $aggregate->views,
            'reads' => $aggregate->reads,
            'share_clicks' => $aggregate->shareClicks,
            'unique_visitors' => $aggregate->uniqueVisitors,
            'average_read_seconds' => $aggregate->averageReadSeconds,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function editorial(int $postId): array
    {
        try {
            $item = $this->services->editorial->item($postId);
        } catch (RuntimeException) {
            return [];
        }

        return [
            'post_id' => $item->postId,
            'editorial_state' => $item->editorialState,
            'native_status' => $item->nativeStatus,
            'assignee_id' => $item->assigneeId,
            'due_at' => $item->dueAt?->format(DATE_ATOM),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function authors(): array
    {
        return $this->services->authors->authors(...$this->range());
    }

    /**
     * @return list<array{target:string,label:string,url:string}>
     */
    private function shareControls(int $postId): array
    {
        return $this->services->sharing->controls(
            $postId,
            get_permalink($postId) ?: '',
            get_the_title($postId),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function comment(CommentModerationItem $comment): array
    {
        return [
            'comment_id' => $comment->commentId,
            'post_id' => $comment->postId,
            'author' => $comment->author,
            'state' => $comment->state,
            'first_comment' => $comment->firstComment,
            'likely_spam' => $comment->likelySpam,
            'held_for_review' => $comment->heldForReview,
        ];
    }

    /**
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     */
    private function range(): array
    {
        $until = new DateTimeImmutable('+1 day');

        return [$until->modify('-30 days'), $until];
    }
}
