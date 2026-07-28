<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides;

defined('ABSPATH') || exit;

use Corex\Admin\AdminPage;

/**
 * The Guides screen: every available guide, grouped, searchable, in the Corex admin shell
 * (spec 084, FR-011 / FR-012 / FR-014).
 *
 * Server-rendered, like every other Corex screen. The content is already on the server — it is PHP
 * value objects — so a React island here would mean shipping a bundle to re-render text that was
 * already in the response. The one interactive part, search, filters DOM that is already present.
 *
 * **No capability of its own beyond reading the admin.** Guides are gated individually
 * ({@see Guide::requiring()}), so an editor who can open three of eight guides sees three; refusing
 * them the whole screen would be refusing them the help for the screens they *can* use.
 */
final class GuidesScreen
{
    private string $hook = '';

    public function __construct(
        private readonly GuideRegistry $registry,
        private readonly AdminPage $page,
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
            __('Corex Guides', 'corex'),
            __('Guides', 'corex'),
            'read',
            'corex-guides',
            [$this, 'render'],
            60,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        $base = dirname(__DIR__) . '/corex-guides.php';

        wp_enqueue_style('corex-admin-shell');
        wp_enqueue_style(
            'corex-guides',
            plugins_url('assets/guides.css', $base),
            ['corex-admin-shell'],
            COREX_GUIDES_VERSION,
        );
        wp_enqueue_script(
            'corex-guides',
            plugins_url('assets/guides.js', $base),
            [],
            COREX_GUIDES_VERSION,
            true,
        );
        wp_set_script_translations('corex-guides', 'corex');
    }

    public function render(): void
    {
        $grouped = $this->registry->bySection();

        echo $this->page->open( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- AdminPage returns escaped markup.
            'guides',
            __('Guides', 'corex'),
            __('How to do the things this site is for. Guides appear here for the features that are actually installed.', 'corex'),
        );

        echo $grouped === [] ? $this->emptyStateHtml() : $this->searchFieldHtml() . $this->sectionsHtml($grouped); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all branches return escaped markup.

        echo $this->page->close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- AdminPage returns escaped markup.
    }

    /**
     * Shown when nothing is registered, or when nothing registered is available to this reader.
     *
     * The two cases are deliberately not distinguished. To a reader they are the same fact — there
     * is nothing here for you — and the difference is only interesting to a developer, who has the
     * registry.
     */
    private function emptyStateHtml(): string
    {
        return $this->page->state(
            'empty',
            __('No guides yet', 'corex'),
            __('Guides appear here as add-ons and site features register them. If you expected one, the feature it documents may not be active for your account.', 'corex'),
        );
    }

    private function searchFieldHtml(): string
    {
        return '<div class="corex-guides__search">'
            . '<label class="screen-reader-text" for="corex-guides-search">'
            . esc_html__('Search guides', 'corex') . '</label>'
            . '<input type="search" id="corex-guides-search" class="corex-guides__search-input" '
            . 'placeholder="' . esc_attr__('Search guides…', 'corex') . '" autocomplete="off" />'
            . '<p class="corex-guides__no-results" role="status" hidden>'
            . esc_html__('No guide matches that.', 'corex') . '</p>'
            . '</div>';
    }

    /**
     * @param array<string,list<Guide>> $grouped
     */
    private function sectionsHtml(array $grouped): string
    {
        $html = '';

        foreach ($grouped as $section => $guides) {
            $html .= '<section class="corex-guides__section" data-corex-guide-section>'
                . '<h2 class="corex-guides__section-title">' . esc_html($this->sectionLabel($section)) . '</h2>'
                . '<div class="corex-guides__list">';

            foreach ($guides as $guide) {
                $html .= $this->guideHtml($guide);
            }

            $html .= '</div></section>';
        }

        return $html;
    }

    private function guideHtml(Guide $guide): string
    {
        $topics = '';
        foreach ($guide->topics as $topic) {
            $topics .= $this->topicHtml($guide, $topic);
        }

        return '<article class="corex-guides__guide" id="' . esc_attr('guide-' . $guide->id) . '" '
            . 'data-corex-guide data-corex-search="' . esc_attr(strtolower($guide->searchText())) . '">'
            . '<h3 class="corex-guides__guide-title">' . esc_html($guide->title) . '</h3>'
            . ($guide->summary !== ''
                ? '<p class="corex-guides__guide-summary">' . esc_html($guide->summary) . '</p>'
                : '')
            . ($guide->screen !== ''
                ? '<p class="corex-guides__guide-screen"><a href="' . esc_url(admin_url($guide->screen)) . '">'
                    . esc_html__('Open the screen this describes', 'corex') . '</a></p>'
                : '')
            . $topics
            . '</article>';
    }

    private function topicHtml(Guide $guide, GuideTopic $topic): string
    {
        $steps = '';
        foreach ($topic->steps as $step) {
            $steps .= $this->stepHtml($step);
        }

        return '<details class="corex-guides__topic" id="' . esc_attr('guide-' . $guide->id . '-' . $topic->id) . '">'
            . '<summary class="corex-guides__topic-title">' . esc_html($topic->title) . '</summary>'
            . ($topic->intro !== ''
                ? '<p class="corex-guides__topic-intro">' . esc_html($topic->intro) . '</p>'
                : '')
            . '<ol class="corex-guides__steps">' . $steps . '</ol>'
            . '</details>';
    }

    /**
     * The warning renders before the instruction, not after: a caution somebody meets once they have
     * already done the thing is decoration.
     *
     * Nothing here numbers the step: the surrounding `<ol>` does, which keeps the number correct
     * when a step is inserted and gives assistive technology the count for free.
     */
    private function stepHtml(GuideStep $step): string
    {
        return '<li class="corex-guides__step">'
            . ($step->hasWarning()
                ? '<p class="corex-guides__step-warning"><strong>'
                    . esc_html__('Before you do this:', 'corex') . '</strong> '
                    . esc_html($step->warning) . '</p>'
                : '')
            . '<p class="corex-guides__step-instruction">' . esc_html($step->instruction) . '</p>'
            . ($step->result !== ''
                ? '<p class="corex-guides__step-result">' . esc_html($step->result) . '</p>'
                : '')
            . $this->screenshotHtml($step)
            . '</li>';
    }

    private function screenshotHtml(GuideStep $step): string
    {
        if ($step->screenshot === null) {
            return '';
        }

        $base = dirname(__DIR__) . '/corex-guides.php';
        $file = dirname(__DIR__) . '/assets/screenshots/' . $step->screenshot->fileName();

        // A guide that references a shot nobody captured renders without it rather than with a
        // broken image. The capture script is what fails loudly about the gap (FR-015); this screen
        // is read by somebody who cannot act on it.
        if (! is_file($file)) {
            return '';
        }

        return '<img class="corex-guides__shot" loading="lazy" decoding="async" src="'
            . esc_url(plugins_url('assets/screenshots/' . $step->screenshot->fileName(), $base))
            . '" alt="' . esc_attr($step->screenshot->alt) . '" />';
    }

    private function sectionLabel(string $section): string
    {
        return match ($section) {
            'content' => __('Content', 'corex'),
            'communication' => __('Messages and email', 'corex'),
            'operations' => __('Running the site', 'corex'),
            Guide::SECTION_GENERAL => __('General', 'corex'),
            // A section a site invented. Title-casing its key beats inventing a label for it or
            // dropping the guides into "General", where the site's own grouping would be lost.
            default => ucwords(str_replace(['-', '_'], ' ', $section)),
        };
    }
}
