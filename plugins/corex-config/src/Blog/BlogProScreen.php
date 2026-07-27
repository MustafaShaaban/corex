<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use Corex\Admin\AdminPage;
use Corex\Config\AdminUi\ScreenAsset;
use Corex\Security\Admin\AdminGuard;
use DateTimeImmutable;
use RuntimeException;

/**
 * Functional Blog Pro admin surface over native WordPress posts, comments, authors, and first-party analytics.
 */
final class BlogProScreen
{
    private string $hook = '';

    /**
     * The analytics window, in days.
     *
     * Named because the panel has to state it: "1,204 views" means nothing without "in the last 30
     * days", and the old panel showed the number alone (spec 075, FR-4).
     */
    private const PERIOD_DAYS = 30;

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
            ScreenAsset::version(dirname(COREX_CONFIG_FILE) . '/assets/blog-pro.css'),
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
        $selectedPostId = $this->selectedPostId($posts);

        return [
            'restUrl' => esc_url_raw(rest_url('corex/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'posts' => $posts,
            'selectedPostId' => $selectedPostId,
            // Which post every panel below is describing. The screen used to compute all of this for
            // whichever post sorted first and name it nowhere, so four large numbers under
            // "First-party reading signals" read as a site-wide total (spec 075, D1/FR-1).
            'selectedPost' => $this->postSummary($posts, $selectedPostId),
            'periodDays' => self::PERIOD_DAYS,
            'analytics' => $selectedPostId > 0 ? $this->analytics($selectedPostId) : null,
            'editorial' => $selectedPostId > 0 ? $this->editorial($selectedPostId) : null,
            'comments' => $selectedPostId > 0 ? array_map($this->comment(...), $this->services->comments->queue($selectedPostId)) : [],
            'authors' => $this->authors(),
            'shareControls' => $selectedPostId > 0 ? $this->shareControls($selectedPostId) : [],
            // What this actor may do, from the same capabilities the REST routes enforce, so a control
            // is hidden rather than shown and refused (DECISIONS #159).
            'can' => [
                'moderate' => current_user_can('moderate_comments'),
                'publish' => current_user_can('publish_posts'),
            ],
        ];
    }

    /**
     * The post the screen is about: `?post=<id>` when it names one we actually listed, else the newest.
     *
     * Validated against the list rather than trusted, so a stale or hand-typed id falls back to
     * something real instead of rendering a screen full of empty panels about nothing.
     *
     * @param list<array{id:int,title:string,status:string,permalink:string}> $posts
     */
    private function selectedPostId(array $posts): int
    {
        $requested = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;

        if ($requested > 0 && in_array($requested, array_column($posts, 'id'), true)) {
            return $requested;
        }

        return (int) ($posts[0]['id'] ?? 0);
    }

    /**
     * @param  list<array{id:int,title:string,status:string,permalink:string}> $posts
     * @return array<string,mixed>|null
     */
    private function postSummary(array $posts, int $selectedPostId): ?array
    {
        foreach ($posts as $post) {
            if ($post['id'] === $selectedPostId) {
                return [...$post, 'status_label' => BlogProLabels::nativeStatus($post['status'])];
            }
        }

        return null;
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
            // Whether analytics saw this post at all. Without it a zero is ambiguous between "nobody
            // opened it" and "we have never seen it", which are opposite problems (spec 075, FR-4).
            'has_data' => $aggregate->hasData,
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

        return BlogProPresenter::editorialItem($item);
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
        return BlogProPresenter::commentItem($comment);
    }

    /**
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     */
    private function range(): array
    {
        $until = new DateTimeImmutable('+1 day');

        return [$until->modify('-' . self::PERIOD_DAYS . ' days'), $until];
    }
}
