<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides;

defined('ABSPATH') || exit;

use WP_Screen;

/**
 * Puts a guide in the Help tab of the screen it describes (spec 084, FR-013).
 *
 * WordPress has had this surface since 3.3 and this repository uses it **zero times** — a
 * contextual-help panel on every admin screen, sitting empty. A guide that names the screen it is
 * about can fill it for free, which is worth more than the Guides screen itself: somebody stuck on
 * a screen looks at that screen, not at a menu item they have not noticed.
 *
 * The tab carries the guide's summary and a link, not the whole guide. Help panels are small, they
 * cannot be searched, and duplicating the steps into one would be a second copy to keep in step
 * with the first.
 */
final class ContextualHelp
{
    public function __construct(private readonly GuideRegistry $registry)
    {
    }

    public function register(): void
    {
        // `current_screen` and not `admin_head`: the screen object does not exist earlier, and
        // `add_help_tab()` is a method on it.
        add_action('current_screen', [$this, 'addTabs']);
    }

    public function addTabs(WP_Screen $screen): void
    {
        $guides = $this->registry->forScreen($this->addressOf($screen));

        if ($guides === []) {
            return;
        }

        foreach ($guides as $guide) {
            $screen->add_help_tab([
                'id' => 'corex-guide-' . $guide->id,
                'title' => $guide->title,
                'content' => $this->tabContentHtml($guide),
            ]);
        }
    }

    /**
     * The admin address a screen corresponds to, in the form a guide declares with `onScreen()`.
     *
     * Built from the screen object rather than read from `$_SERVER['REQUEST_URI']`, so it is the
     * same string whatever query arguments a particular visit carried — a guide should not stop
     * appearing because somebody arrived with `&paged=2` on the URL.
     */
    private function addressOf(WP_Screen $screen): string
    {
        $base = $screen->base === 'edit' ? 'edit.php' : $screen->base . '.php';

        // A Corex settings page is `admin.php?page=<slug>`; `$screen->base` for those is the
        // generated hook name, which is not an address anybody would write in a guide.
        if ($screen->id !== '' && str_contains($screen->id, '_page_')) {
            $slug = substr($screen->id, strrpos($screen->id, '_page_') + 6);

            return 'admin.php?page=' . $slug;
        }

        // `post` is addressed as plain `edit.php`, because that is the address WordPress itself
        // uses and therefore the one anybody writes in a guide. Emitting `edit.php?post_type=post`
        // here is correct-looking and matches nothing — which is exactly what it did on the first
        // attempt, and the browser test is what caught it.
        if ($screen->post_type !== '' && $screen->post_type !== 'post'
            && in_array($screen->base, ['edit', 'post'], true)
        ) {
            return $base . '?post_type=' . $screen->post_type;
        }

        return $base;
    }

    private function tabContentHtml(Guide $guide): string
    {
        $topics = '';
        foreach ($guide->topics as $topic) {
            $topics .= '<li>' . esc_html($topic->title) . '</li>';
        }

        return '<p>' . esc_html($guide->summary !== '' ? $guide->summary : $guide->title) . '</p>'
            . ($topics !== '' ? '<ul>' . $topics . '</ul>' : '')
            . '<p><a href="' . esc_url(admin_url('admin.php?page=corex-guides#guide-' . $guide->id)) . '">'
            . esc_html__('Read the full guide', 'corex') . '</a></p>';
    }
}
