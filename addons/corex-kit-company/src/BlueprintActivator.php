<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit;

use Corex\Kit\Setup\ConflictResolver;
use Corex\Multisite\PluginActivationInspector;
use Corex\Provisioning\ApplyOutcome;
use Corex\Provisioning\PageContent;
use Corex\Provisioning\PageDisposition;
use Corex\Provisioning\PagePlanner;
use Corex\Provisioning\PageRecord;

defined('ABSPATH') || exit;

/**
 * Applies a planned kit: enables its feature flags, activates its module plugins, and seeds the kit's pages
 * (composing its patterns; one becomes the front page). The side-effecting half of the setup wizard, kept
 * apart from the admin screen (one actor: site provisioning).
 *
 * Page handling is classified by the pure {@see PagePlanner} into create / adopt / skip (spec 041): a page
 * whose slug is absent is created; a pre-existing **empty** page (or an un-populated kit placeholder) is
 * **adopted** — populated in place; a page that already holds user content is skipped untouched. The front
 * page is set after the loop whenever the declared home was created or adopted, so a kit apply never leaves a
 * blank front page. Each page records its disposition in `_corex_kit_page` (`created` | `adopted` |
 * `replaced` | `suffixed`), and a page the kit replaces or adopts first records what it was
 * ({@see PageRecord}), as do the reading settings before the front page is set, so a reset can put the
 * owner's pages back rather than delete them (spec 115). Returns an {@see ApplyOutcome} the wizard renders
 * as a summary (and spec 042 reuses).
 */
final class BlueprintActivator
{
    public const SEEDED_OPTION = 'corex_kit_seeded_pages';
    public const PAGE_META     = PageDisposition::META;

    private const MODULE_FILES = [
        'corex-ui'          => 'corex-ui/corex-ui.php',
        'corex-forms'       => 'corex-forms/corex-forms.php',
        'corex-email'       => 'corex-email/corex-email.php',
        'corex-blocks'      => 'corex-blocks/corex-blocks.php',
        'corex-kit-company' => 'corex-kit-company/corex-kit-company.php',
    ];

    public function __construct(
        private readonly PluginActivationInspector $pluginActivationInspector,
        private readonly PagePlanner $planner = new PagePlanner(),
        private readonly PageContent $content = new PageContent(),
        private readonly ConflictResolver $conflicts = new ConflictResolver(),
    ) {
    }

    /**
     * @param array{flags:list<string>,modules:list<string>,pages?:list<array{title:string,slug:string,content:string,front?:bool}>} $plan
     * @param array<string,string> $choices slug => keep|replace|suffix for conflicting pages (default keep)
     */
    public function apply(array $plan, array $choices = []): ApplyOutcome
    {
        $flags   = $this->enableFlags($plan['flags']);
        $modules = $this->activateModules($plan['modules']);

        return $this->seedPages($plan['pages'] ?? [], $modules, $flags, $choices);
    }

    /**
     * Classify, then create or populate each declared page (idempotent, never overwriting user content), set
     * the front page when the declared home was created or adopted, and record dispositions for a safe reset.
     *
     * @param list<array{title:string,slug:string,content:string,front?:bool}> $pages
     * @param list<string>                                                      $modules
     * @param list<string>                                                      $flags
     */
    /**
     * Read-only: classify the declared pages against current site state (no writes) — the basis of the
     * activation preview (spec 042). The same classification a real apply uses.
     *
     * @param list<array{title:string,slug:string,content:string,front?:bool}> $pages
     *
     * @return list<\Corex\Provisioning\PageDisposition>
     */
    public function classify(array $pages): array
    {
        return $this->planner->plan($pages, $this->signals($pages));
    }

    public function seedPages(array $pages, array $modules = [], array $flags = [], array $choices = []): ApplyOutcome
    {
        $dispositions = $this->classify($pages);

        if ($choices !== []) {
            // Apply explicit operator conflict choices (FR-139). The resolver defaults every
            // unchosen conflict to Keep Mine, so nothing is overwritten silently (FR-143).
            $dispositions = $this->conflicts->resolve($dispositions, $choices);
        }

        $bySlug = [];
        foreach ($pages as $page) {
            $bySlug[$page['slug']] = $page;
        }

        $seeded      = array_values(array_map('intval', (array) get_option(self::SEEDED_OPTION, [])));
        $results     = [];
        $frontPageId = null;

        foreach ($dispositions as $disposition) {
            $page   = $bySlug[$disposition->slug];
            $result = $this->persist($disposition, $page);

            if ($result['pageId'] !== null && ! in_array($result['pageId'], $seeded, true)) {
                $seeded[] = $result['pageId'];
            }

            $isFront = ($page['front'] ?? false) === true
                && $result['pageId'] !== null
                && $disposition->action !== PageDisposition::SKIP;

            if ($isFront) {
                $frontPageId = $result['pageId'];
            }

            $results[] = [
                'disposition' => $disposition,
                'pageId'      => $result['pageId'],
                'isFront'     => $isFront,
                'persistedAs' => $result['persistedAs'],
            ];
        }

        if ($frontPageId !== null) {
            $this->recordReading();
            update_option('show_on_front', 'page');
            update_option('page_on_front', $frontPageId);
        }

        update_option(self::SEEDED_OPTION, array_values(array_unique($seeded)));

        return new ApplyOutcome($results, $modules, $flags, $frontPageId);
    }

    /**
     * Create or populate one page per its disposition; skip records nothing.
     *
     * @param array{title:string,slug:string,content:string,front?:bool} $page
     *
     * @return array{pageId:?int,persistedAs:?string}
     */
    private function persist(PageDisposition $disposition, array $page): array
    {
        // WordPress takes one level of slashes off a title and a content on their way in, and a
        // block's attributes are JSON, in which an ampersand and a quote are written with one.
        if ($disposition->action === PageDisposition::CREATE) {
            $id = wp_insert_post([
                'post_title'   => wp_slash($page['title']),
                'post_name'    => $page['slug'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => wp_slash($page['content']),
            ]);

            if (! is_int($id) || $id <= 0) {
                return ['pageId' => null, 'persistedAs' => null];
            }

            update_post_meta($id, self::PAGE_META, PageDisposition::PERSISTED_CREATED);

            return ['pageId' => $id, 'persistedAs' => PageDisposition::PERSISTED_CREATED];
        }

        if ($disposition->action === PageDisposition::ADOPT) {
            $existing = get_page_by_path($page['slug']);

            if (! $existing instanceof \WP_Post) {
                return ['pageId' => null, 'persistedAs' => null];
            }

            if ($disposition->reason !== 'kit_placeholder') {
                $this->recordPage($existing);
            }

            wp_update_post(['ID' => $existing->ID, 'post_content' => wp_slash($page['content'])]);

            // A kit placeholder stays Corex-owned (created → deleted on reset); a user's empty page becomes
            // adopted (reset only empties it, never deletes a page the user already had).
            $persistedAs = $disposition->reason === 'kit_placeholder'
                ? PageDisposition::PERSISTED_CREATED
                : PageDisposition::PERSISTED_ADOPTED;
            update_post_meta($existing->ID, self::PAGE_META, $persistedAs);

            return ['pageId' => $existing->ID, 'persistedAs' => $persistedAs];
        }

        // Replace only runs from an explicit operator conflict choice (never a default), so existing
        // content is never overwritten silently (FR-143). What the page was is recorded first, so a reset
        // puts it back (spec 115).
        if ($disposition->action === PageDisposition::REPLACE) {
            $existing = get_page_by_path($disposition->slug);

            if (! $existing instanceof \WP_Post) {
                return ['pageId' => null, 'persistedAs' => null];
            }

            // A page an earlier apply created stays the kit's: replacing its content records nothing,
            // and a reset still removes it.
            $earlier = (string) get_post_meta($existing->ID, self::PAGE_META, true);
            if (in_array($earlier, [PageDisposition::PERSISTED_CREATED, PageDisposition::PERSISTED_SUFFIXED], true)) {
                wp_update_post(['ID' => $existing->ID, 'post_content' => wp_slash($page['content'])]);

                return ['pageId' => $existing->ID, 'persistedAs' => $earlier];
            }

            $this->recordPage($existing);
            wp_update_post(['ID' => $existing->ID, 'post_content' => wp_slash($page['content'])]);
            update_post_meta($existing->ID, self::PAGE_META, PageDisposition::PERSISTED_REPLACED);

            return ['pageId' => $existing->ID, 'persistedAs' => PageDisposition::PERSISTED_REPLACED];
        }

        // Suffix leaves the operator's page untouched and creates the kit page under a fresh, non-colliding slug.
        if ($disposition->action === PageDisposition::SUFFIX) {
            $id = wp_insert_post([
                'post_title'   => wp_slash($page['title']),
                'post_name'    => $disposition->persistSlug(),
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => wp_slash($page['content']),
            ]);

            if (! is_int($id) || $id <= 0) {
                return ['pageId' => null, 'persistedAs' => null];
            }

            update_post_meta($id, self::PAGE_META, PageDisposition::PERSISTED_SUFFIXED);

            return ['pageId' => $id, 'persistedAs' => PageDisposition::PERSISTED_SUFFIXED];
        }

        return ['pageId' => null, 'persistedAs' => null];
    }

    /**
     * Record what a page was before the kit changes it, once: a second apply keeps the first record, so a
     * reset returns the page to what the owner had before any kit.
     */
    private function recordPage(\WP_Post $page): void
    {
        if (PageRecord::isRecord(get_post_meta($page->ID, PageRecord::META, true))) {
            return;
        }

        $template = (string) get_post_meta($page->ID, PageRecord::TEMPLATE_META, true);

        // Post meta takes one level of slashes off on the way in, as a content does.
        update_post_meta($page->ID, PageRecord::META, wp_slash(PageRecord::of($page, $template)));
    }

    /**
     * Record the site's reading settings before the kit sets its front page, once.
     */
    private function recordReading(): void
    {
        if (PageRecord::isReadingRecord(get_option(PageRecord::READING_OPTION, null))) {
            return;
        }

        $reading = [];
        foreach (PageRecord::READING_KEYS as $key) {
            $value         = get_option($key, '');
            $reading[$key] = is_scalar($value) ? (string) $value : '';
        }

        update_option(PageRecord::READING_OPTION, $reading, false);
    }

    /**
     * Per-slug signal (exists / is-empty / is-kit-placeholder) for the pure planner.
     *
     * @param list<array{slug:string}> $pages
     *
     * @return array<string,array{exists:bool,isEmpty:bool,isKitPlaceholder:bool}>
     */
    private function signals(array $pages): array
    {
        $signals = [];

        foreach ($pages as $page) {
            $existing = get_page_by_path($page['slug']);

            if (! $existing instanceof \WP_Post) {
                $signals[$page['slug']] = ['exists' => false, 'isEmpty' => false, 'isKitPlaceholder' => false];

                continue;
            }

            $isEmpty    = $this->content->isBlank((string) get_post_field('post_content', $existing->ID));
            $hasKitMeta = (string) get_post_meta($existing->ID, self::PAGE_META, true) !== '';

            $signals[$page['slug']] = [
                'exists'           => true,
                'isEmpty'          => $isEmpty,
                'isKitPlaceholder' => $hasKitMeta && $isEmpty,
            ];
        }

        return $signals;
    }

    /**
     * @param list<string> $flags
     *
     * @return list<string> the flags enabled
     */
    private function enableFlags(array $flags): array
    {
        foreach ($flags as $flag) {
            update_option('corex_features_' . sanitize_key($flag), '1');
        }

        return $flags;
    }

    /**
     * @param list<string> $modules
     *
     * @return list<string> the modules that were activated
     */
    private function activateModules(array $modules): array
    {
        if (! function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $activated = [];

        foreach ($modules as $module) {
            $file = self::MODULE_FILES[$module] ?? null;

            if ($file !== null && ! $this->pluginActivationInspector->isActive($file)) {
                activate_plugin($file); // returns WP_Error on failure; non-fatal here
                $activated[] = $module;
            }
        }

        return $activated;
    }
}
