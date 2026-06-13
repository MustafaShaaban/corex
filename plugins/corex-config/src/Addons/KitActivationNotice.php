<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

use Corex\Provisioning\ApplyOutcome;
use Corex\Provisioning\KitProvisioner;
use Corex\Provisioning\PageDisposition;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The kit activation prompt (spec 042): when a kit is enabled but not yet applied, this renders a dismissible
 * admin banner previewing exactly what applying would do (pages created / populated / skipped, the front page,
 * the modules) with Apply / Not now actions. Nothing changes until the admin chooses Apply — the preview is
 * read-only. Apply routes through the shared {@see KitProvisioner::apply()} and a "what changed" summary is
 * shown. Both actions are cap + nonce gated via the shared {@see AdminGuard} (Principle VII admin-screen rule).
 */
final class KitActivationNotice
{
    private ?ApplyOutcome $applied = null;

    private string $appliedKit = '';

    public function __construct(
        private readonly KitProvisioner $provisioner,
        private readonly KitActivationView $view,
        private readonly PendingKits $pending,
        private readonly AdminGuard $guard,
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle']);
        add_action('admin_notices', [$this, 'notices']);
    }

    public function handle(): void
    {
        if (! isset($_POST['corex_kit_action'], $_POST['corex_kit'])) {
            return;
        }

        if (! $this->guard->verifiedPost('corex_kit_nonce', 'corex_kit_activation')) {
            return;
        }

        $kit    = sanitize_key(wp_unslash($_POST['corex_kit']));
        $action = sanitize_key(wp_unslash($_POST['corex_kit_action']));

        if (! $this->provisioner->isApplicable($kit)) {
            return;
        }

        if ($action === 'apply') {
            $this->applied    = $this->provisioner->apply($kit);
            $this->appliedKit = $kit;
        }

        // Both Apply and "Not now" clear the pending prompt.
        $this->pending->remove($kit);
    }

    public function notices(): void
    {
        if (! $this->guard->authorized()) {
            return;
        }

        if ($this->applied !== null) {
            $this->renderSummary($this->appliedKit, $this->applied);

            return;
        }

        foreach ($this->pending->all() as $kit) {
            if ($this->provisioner->isApplicable($kit)) {
                $this->renderPrompt($kit);
            }
        }
    }

    private function renderPrompt(string $kit): void
    {
        $prompt = $this->view->prompt($this->provisioner->preview($kit));

        echo '<div class="notice notice-info"><p><strong>'
            . esc_html(sprintf(/* translators: %s: kit name */ __('The "%s" kit is ready.', 'corex'), $kit))
            . '</strong> ' . esc_html__('Apply its starter content?', 'corex') . '</p>';

        echo '<ul class="ul-disc">';
        foreach ($prompt['rows'] as $row) {
            echo '<li>' . esc_html($row['title']) . ' — ' . esc_html($this->actionLabel($row['action'])) . '</li>';
        }
        echo '</ul>';

        echo '<form method="post"><p>';
        echo wp_nonce_field('corex_kit_activation', 'corex_kit_nonce', true, false);
        echo '<input type="hidden" name="corex_kit" value="' . esc_attr($kit) . '" />';
        echo '<button type="submit" name="corex_kit_action" value="apply" class="button button-primary">'
            . esc_html__('Apply', 'corex') . '</button> ';
        echo '<button type="submit" name="corex_kit_action" value="dismiss" class="button">'
            . esc_html__('Not now', 'corex') . '</button>';
        echo '</p></form></div>';
    }

    private function renderSummary(string $kit, ApplyOutcome $outcome): void
    {
        $summary = $this->view->summary($outcome);

        echo '<div class="notice notice-success is-dismissible"><p><strong>'
            . esc_html(sprintf(/* translators: %s: kit name */ __('Applied the "%s" kit.', 'corex'), $kit))
            . '</strong></p><p>'
            . esc_html(sprintf(
                /* translators: 1: created count, 2: populated count, 3: skipped count */
                __('%1$d page(s) created, %2$d populated, %3$d left unchanged.', 'corex'),
                $summary['created'],
                $summary['populated'],
                $summary['skipped'],
            ))
            . '</p>';

        echo '<ul class="ul-disc">';
        foreach ($summary['rows'] as $row) {
            $link = is_int($row['pageId'])
                ? '<a href="' . esc_url((string) get_edit_post_link($row['pageId'])) . '">' . esc_html($row['title']) . '</a>'
                : esc_html($row['title']);

            echo '<li>' . $link . ' — ' . esc_html($this->actionLabel($row['action']))
                . ($row['isFront'] ? ' ' . esc_html__('(front page)', 'corex') : '') . '</li>';
        }
        echo '</ul>';

        echo '<p><a href="' . esc_url(home_url('/')) . '">' . esc_html__('View site', 'corex') . '</a></p></div>';
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            PageDisposition::CREATE => __('will be created', 'corex'),
            PageDisposition::ADOPT  => __('will be filled in', 'corex'),
            default                 => __('left unchanged (already has content)', 'corex'),
        };
    }
}
