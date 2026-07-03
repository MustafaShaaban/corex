<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

use Corex\Admin\AdminPage;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Blog Pro reference surface (spec 067, design: "Corex Blog Pro & Analytics"). Blog Pro is a future
 * add-on; the owner requires the designed surface visible NOW for review, shown honestly. This screen:
 *  - opens with a prominent "future add-on — reference only, not live" ribbon;
 *  - shows four tabs (Analytics, Editorial queue, Comments, Authors);
 *  - renders Analytics as an explicitly-labelled SAMPLE layout (no live metrics, no trackers);
 *  - renders Editorial, Comments, and Authors from REAL WordPress facts (post-status counts, comment
 *    counts, real authors), with moderation/management linking to the native WordPress screens.
 * It never fabricates live analytics, and never implements purchase/licensing/marketplace.
 */
final class BlogProScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly BlogProModel $model,
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
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('blog-pro');

            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        $active = $this->model->activeTab($tab);

        echo $this->page->open(
            'blog-pro',
            __('CoreX Blog Pro', 'corex'),
            __('A reference view of the planned Blog Pro add-on, built on native WordPress posts.', 'corex'),
        );

        echo $this->ribbon();
        echo $this->page->tabs('corex-blog-pro', $this->model->tabs(), $active, __('Blog Pro sections', 'corex'));

        echo match ($active) {
            BlogProModel::EDITORIAL => $this->editorialTab(),
            BlogProModel::COMMENTS  => $this->commentsTab(),
            BlogProModel::AUTHORS   => $this->authorsTab(),
            default                 => $this->analyticsTab(),
        };

        echo $this->page->close();
    }

    /** The honest "future add-on, reference only, not live" ribbon (design: the purple future ribbon). */
    private function ribbon(): string
    {
        return '<div class="corex-surface corex-blogpro__ribbon">'
            . '<span class="corex-blogpro__ribbon-badge">' . esc_html__('FUTURE ADD-ON', 'corex') . '</span>'
            . '<p class="corex-blogpro__ribbon-text">'
            . '<strong>' . esc_html__('Blog Pro is a future add-on — reference only, not part of CoreX Core yet.', 'corex') . '</strong> '
            . esc_html__('It builds on native WordPress posts (never replaces them). The Analytics figures below are sample data for layout review, not live metrics — no third-party trackers. Editorial, Comments, and Authors below show real data from this site.', 'corex')
            . '</p></div>';
    }

    private function analyticsTab(): string
    {
        $ref  = $this->model->analyticsReference();
        $cards = '';
        foreach ($ref['stats'] as $stat) {
            $cards .= '<div class="corex-surface corex-blogpro__stat">'
                . '<div class="corex-blogpro__stat-label">' . esc_html($stat['label']) . '</div>'
                . '<div class="corex-blogpro__stat-value">' . esc_html($stat['value']) . '</div>'
                . '<div class="corex-blogpro__stat-trend">' . esc_html($stat['trend']) . ' '
                . esc_html__('vs prev 30d', 'corex') . '</div></div>';
        }

        return '<section>'
            . '<p class="corex-blogpro__sample">' . esc_html__('Sample data · reference layout, not live metrics.', 'corex') . '</p>'
            . '<div class="corex-blogpro__stats">' . $cards . '</div>'
            . '<p class="corex-blogpro__note">'
            . esc_html__('A first-party, privacy-friendly analytics engine (real views, reads, and engagement) is a planned Blog Pro capability. It is not enabled yet, so these figures are a reference layout only.', 'corex')
            . '</p></section>';
    }

    private function editorialTab(): string
    {
        $counts = $this->postCounts();
        $rows   = $this->countCards($this->model->editorial($counts));

        return '<section>'
            . '<div class="corex-blogpro__stats">' . $rows . '</div>'
            . '<p class="corex-blogpro__note">'
            . esc_html__('Real editorial state from this site. An assignable editorial workflow (owners, due dates, statuses) is a planned Blog Pro capability.', 'corex')
            . ' <a href="' . esc_url(admin_url('edit.php?post_type=post')) . '">' . esc_html__('Manage posts', 'corex') . '</a>'
            . '</p></section>';
    }

    private function commentsTab(): string
    {
        $counts = $this->commentCounts();
        $rows   = $this->countCards($this->model->comments($counts));

        return '<section>'
            . '<div class="corex-blogpro__stats">' . $rows . '</div>'
            . '<p class="corex-blogpro__note">'
            . esc_html__('Real comment state from this site. Moderation happens on the WordPress Comments screen; an in-CoreX moderation queue is a planned Blog Pro capability.', 'corex')
            . ' <a href="' . esc_url(admin_url('edit-comments.php')) . '">' . esc_html__('Moderate comments', 'corex') . '</a>'
            . '</p></section>';
    }

    private function authorsTab(): string
    {
        $authors = $this->model->authors($this->authorRows());

        if ($authors === []) {
            return '<section>' . $this->page->state(
                'empty',
                __('No authors yet', 'corex'),
                __('Users who can publish posts will appear here with their real published-post counts.', 'corex'),
            ) . '</section>';
        }

        $rows = '';
        foreach ($authors as $author) {
            $rows .= '<li class="corex-blogpro__author">'
                . '<span>' . esc_html($author['name']) . '</span>'
                . '<span class="corex-blogpro__author-count">' . sprintf(
                    /* translators: %d: number of published posts by the author */
                    esc_html(_n('%d published post', '%d published posts', $author['posts'], 'corex')),
                    (int) $author['posts'],
                ) . '</span></li>';
        }

        return '<section><ul class="corex-blogpro__authors">' . $rows . '</ul>'
            . '<p class="corex-blogpro__note">'
            . esc_html__('Real authors on this site. Author profiles, bylines, and per-author analytics are planned Blog Pro capabilities.', 'corex')
            . ' <a href="' . esc_url(admin_url('users.php')) . '">' . esc_html__('Manage users', 'corex') . '</a>'
            . '</p></section>';
    }

    /**
     * @param list<array{label:string,count:int,tone:string}> $items
     */
    private function countCards(array $items): string
    {
        $cards = '';
        foreach ($items as $item) {
            $cards .= '<div class="corex-surface corex-blogpro__stat is-' . esc_attr($item['tone']) . '">'
                . '<div class="corex-blogpro__stat-label">' . esc_html($item['label']) . '</div>'
                . '<div class="corex-blogpro__stat-value">' . esc_html(number_format_i18n($item['count'])) . '</div></div>';
        }

        return $cards;
    }

    /**
     * @return array{draft:int,pending:int,future:int,publish:int}
     */
    private function postCounts(): array
    {
        $counts = wp_count_posts('post');

        return [
            'draft'   => (int) ($counts->draft ?? 0),
            'pending' => (int) ($counts->pending ?? 0),
            'future'  => (int) ($counts->future ?? 0),
            'publish' => (int) ($counts->publish ?? 0),
        ];
    }

    /**
     * @return array{approved:int,moderated:int,spam:int,trash:int}
     */
    private function commentCounts(): array
    {
        $counts = wp_count_comments();

        return [
            'approved'  => (int) ($counts->approved ?? 0),
            'moderated' => (int) ($counts->moderated ?? 0),
            'spam'      => (int) ($counts->spam ?? 0),
            'trash'     => (int) ($counts->trash ?? 0),
        ];
    }

    /**
     * @return list<array{name:string,posts:int}>
     */
    private function authorRows(): array
    {
        $users = get_users([
            'capability' => 'edit_posts',
            'number'     => 20,
            'orderby'    => 'display_name',
            'fields'     => ['ID', 'display_name'],
        ]);

        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                'name'  => (string) $user->display_name,
                'posts' => (int) count_user_posts((int) $user->ID, 'post', true),
            ];
        }

        return $rows;
    }
}
