<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit\Setup;

use Corex\Http\ResponseEnvelope;
use Corex\Kit\BlueprintActivator;
use Corex\Kit\Provisioning\KitAppliedRecord;
use Corex\Kit\SetupWizard;
use Corex\Provisioning\PageDisposition;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * REST for the nine-step setup wizard (spec 068 US8, T195). It exposes the wizard configuration
 * (kits, brand fields, demo levels, conflict choices), the live nine-step progress, a plan preview
 * with real conflicts and the existing pages the kit would adopt, and a guarded apply that honours
 * operator conflict choices. Reads require `manage_options`; the apply additionally requires a valid
 * REST nonce (Principle VII) and the person's confirmation that they have a full backup of their own,
 * and never overwrites existing content without an explicit Replace choice (FR-143). CoreX takes no
 * backup: an apply records the pages it replaces or adopts and the front-page settings, so a reset can
 * put them back (spec 115). The pure seams (`config`, `progressState`, `planPreview`) are unit-tested.
 */
final class SetupWizardController
{
    private const PROGRESS_OPTION = 'corex_setup_progress';

    public function __construct(
        private readonly SetupWizard $wizard,
        private readonly SetupProgress $progress,
        private readonly BlueprintActivator $activator,
        private readonly ConflictResolver $conflicts = new ConflictResolver(),
        private readonly KitAppliedRecord $applied = new KitAppliedRecord(),
    ) {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/setup/state', [
            'methods'             => 'GET',
            'callback'            => [$this, 'state'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route('corex/v1', '/setup/plan', [
            'methods'             => 'GET',
            'callback'            => [$this, 'planResponse'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route('corex/v1', '/setup/apply', [
            'methods'             => 'POST',
            'callback'            => [$this, 'applyResponse'],
            'permission_callback' => [$this, 'canApply'],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function canApply(WP_REST_Request $request): bool
    {
        return $this->canManage()
            && wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest') !== false;
    }

    /**
     * The static wizard configuration — real kits, brand fields, demo levels, and conflict choices.
     *
     * @return array{kits:list<array<string,mixed>>,brandFields:list<array{key:string,label:string}>,demoLevels:list<array<string,string>>,conflictChoices:list<array<string,string>>}
     */
    public function config(): array
    {
        return [
            'kits'            => $this->wizard->kits(),
            'brandFields'     => $this->wizard->brandFields(),
            'demoLevels'      => $this->wizard->demoLevels(),
            'conflictChoices' => $this->wizard->conflictChoices(),
        ];
    }

    /**
     * The nine-step progress from the persisted completed-step + blocker facts.
     *
     * @return array<string,mixed>
     */
    public function progressState(): array
    {
        $stored    = (array) get_option(self::PROGRESS_OPTION, []);
        $completed = array_values(array_map('strval', (array) ($stored['completed'] ?? [])));
        $skipped   = array_values(array_map('strval', (array) ($stored['skipped'] ?? [])));

        return $this->progress->state($completed, [], $skipped);
    }

    /**
     * A read-only plan preview for a kit at a demo level: the modules/flags/pages, the real conflicting
     * pages the operator must choose about, and the existing pages the kit would adopt — no writes. Each
     * conflict and adopted page carries `pageTitle`, the title the owner's page has now, which is what the
     * Backup step lists as recorded, and `recorded`, false for a page an earlier apply made.
     *
     * @return array{plan:array<string,mixed>,conflicts:list<array{slug:string,title:string,pageTitle:string,recorded:bool}>,adopted:list<array{slug:string,title:string,pageTitle:string,recorded:bool}>}
     */
    public function planPreview(string $kit, string $level): array
    {
        $plan         = $this->wizard->plan($kit, $level);
        $dispositions = $this->activator->classify($plan['pages']);

        return [
            'plan'      => $plan,
            'conflicts' => $this->withPageTitles($this->conflicts->conflicts($dispositions)),
            'adopted'   => $this->withPageTitles($this->adopted($dispositions)),
        ];
    }

    /**
     * The owner's existing pages the plan fills in place (an empty page they made, not a kit placeholder).
     *
     * @param list<PageDisposition> $dispositions
     *
     * @return list<array{slug:string,title:string}>
     */
    private function adopted(array $dispositions): array
    {
        $adopted = [];

        foreach ($dispositions as $disposition) {
            if ($disposition->action === PageDisposition::ADOPT && $disposition->reason !== 'kit_placeholder') {
                $adopted[] = ['slug' => $disposition->slug, 'title' => $disposition->title];
            }
        }

        return $adopted;
    }

    /**
     * Each row with the title its page has now, and whether the page is the owner's: a page an earlier
     * apply created stays the kit's when it is replaced, so nothing of it is recorded (spec 115).
     *
     * @param list<array{slug:string,title:string}> $rows
     *
     * @return list<array{slug:string,title:string,pageTitle:string,recorded:bool}>
     */
    private function withPageTitles(array $rows): array
    {
        $kitsOwn = [PageDisposition::PERSISTED_CREATED, PageDisposition::PERSISTED_SUFFIXED];

        return array_map(static function (array $row) use ($kitsOwn): array {
            $page  = get_page_by_path($row['slug']);
            $title = $page instanceof \WP_Post ? (string) $page->post_title : '';
            $made  = $page instanceof \WP_Post
                && in_array((string) get_post_meta($page->ID, PageDisposition::META, true), $kitsOwn, true);

            return $row + ['pageTitle' => $title !== '' ? $title : $row['title'], 'recorded' => ! $made];
        }, $rows);
    }

    public function state(WP_REST_Request $request): WP_REST_Response
    {
        return $this->success([
            'config'   => $this->config(),
            'progress' => $this->progressState(),
        ]);
    }

    public function planResponse(WP_REST_Request $request): WP_REST_Response
    {
        $kit   = sanitize_key((string) $request->get_param('kit'));
        $level = sanitize_key((string) $request->get_param('level')) ?: 'standard';

        return $this->success($this->planPreview($kit, $level));
    }

    public function applyResponse(WP_REST_Request $request): WP_REST_Response
    {
        if ((bool) $request->get_param('confirm') !== true) {
            return new WP_REST_Response(
                ResponseEnvelope::error('confirmation_required', __('Confirm you have a full backup of this site before applying. CoreX records the pages this plan replaces or adopts and your front-page settings, so a reset can put them back. It does not back up anything else.', 'corex'))->toArray(),
                422,
            );
        }

        $kit     = sanitize_key((string) $request->get_param('kit'));
        $level   = sanitize_key((string) $request->get_param('level')) ?: 'standard';
        $choices = $this->sanitizeChoices((array) $request->get_param('choices'));
        $plan    = $this->wizard->plan($kit, $level);

        if ($plan['pages'] === [] && $plan['modules'] === []) {
            return new WP_REST_Response(
                ResponseEnvelope::error('unknown_kit', __('Unknown kit.', 'corex'))->toArray(),
                404,
            );
        }

        $outcome = $this->activator->apply($plan, $choices);

        // Recorded as the Add-ons prompt records it, and only when every page was written, so the
        // Overview's kit row and Add-ons say the same (spec 118, US-OVR-09).
        return $this->success([
            'applied' => $this->applied->record($kit, $outcome),
            'pages'   => count($outcome->pages()),
        ]);
    }

    /**
     * @param array<string,mixed> $choices
     *
     * @return array<string,string>
     */
    private function sanitizeChoices(array $choices): array
    {
        $allowed = [PageDisposition::SKIP => 'keep', 'keep' => 'keep', 'replace' => 'replace', 'suffix' => 'suffix'];
        $clean   = [];

        foreach ($choices as $slug => $choice) {
            $choice = (string) $choice;
            if (isset($allowed[$choice])) {
                $clean[sanitize_title((string) $slug)] = $allowed[$choice];
            }
        }

        return $clean;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function success(array $data): WP_REST_Response
    {
        return new WP_REST_Response(ResponseEnvelope::success($data)->toArray());
    }
}
