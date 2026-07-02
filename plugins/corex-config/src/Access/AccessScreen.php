<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

use Corex\Admin\AdminPage;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Access & Abilities baseline screen (spec 065): a truthful, read-only view of who can do what.
 * It reads the REAL WordPress roles and the capabilities CoreX actually checks (CoreX admin is gated on
 * `manage_options`), renders a role × capability matrix, the current user's permissions, and the
 * capability each CoreX screen requires. It performs no capability mutation — a full role editor /
 * advanced AAM is deliberately deferred, stated honestly, never faked. Access is AdminGuard-gated.
 */
final class AccessScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly AccessMatrix $matrix,
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
            __('CoreX Access & Abilities', 'corex'),
            __('Access & Abilities', 'corex'),
            'manage_options',
            'corex-access',
            [$this, 'render'],
            32,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        wp_enqueue_style(
            'corex-access',
            plugins_url('assets/access.css', COREX_CONFIG_FILE),
            ['corex-admin-shell'],
            '1.0.0',
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('access');

            return;
        }

        echo $this->page->open(
            'access',
            __('CoreX Access & Abilities', 'corex'),
            __('Who can access CoreX and manage content on this site — read from the real WordPress roles.', 'corex'),
        );

        echo $this->currentUserCard();
        echo $this->matrixCard($this->matrix->build($this->roles()));
        echo $this->requirementsCard();
        echo $this->deferralNote();
        echo $this->page->close();
    }

    private function currentUserCard(): string
    {
        $rows = '';
        foreach ($this->matrix->forUser($this->currentUserCaps()) as $ability) {
            $rows .= '<li class="corex-access__you is-' . ($ability['granted'] ? 'yes' : 'no') . '">'
                . '<span>' . esc_html($ability['label']) . '</span>'
                . '<span class="corex-access__flag">'
                . esc_html($ability['granted'] ? __('Yes', 'corex') : __('No', 'corex')) . '</span></li>';
        }

        return '<section class="corex-surface corex-access__card">'
            . '<header class="corex-access__head"><h2>' . esc_html__('Your permissions', 'corex') . '</h2></header>'
            . '<ul class="corex-access__you-list">' . $rows . '</ul></section>';
    }

    /**
     * @param array{roles:list<array{key:string,name:string}>,rows:list<array{key:string,label:string,cap:string,cells:array<string,bool>}>} $matrix
     */
    private function matrixCard(array $matrix): string
    {
        $head = '<th>' . esc_html__('Capability', 'corex') . '</th>';
        foreach ($matrix['roles'] as $role) {
            $head .= '<th>' . esc_html($role['name']) . '</th>';
        }

        $body = '';
        foreach ($matrix['rows'] as $row) {
            $cells = '<th scope="row">' . esc_html($row['label'])
                . '<code class="corex-access__cap">' . esc_html($row['cap']) . '</code></th>';
            foreach ($matrix['roles'] as $role) {
                $granted = ! empty($row['cells'][$role['key']]);
                $cells  .= '<td class="is-' . ($granted ? 'yes' : 'no') . '">'
                    . '<span class="screen-reader-text">'
                    . esc_html($granted ? __('Granted', 'corex') : __('Not granted', 'corex')) . '</span>'
                    . ($granted ? '&#10003;' : '&#8211;') . '</td>';
            }
            $body .= '<tr>' . $cells . '</tr>';
        }

        return '<section class="corex-surface corex-access__card">'
            . '<header class="corex-access__head"><h2>' . esc_html__('Role capability matrix', 'corex') . '</h2></header>'
            . '<div class="corex-access__scroll"><table class="corex-access__matrix"><thead><tr>' . $head
            . '</tr></thead><tbody>' . $body . '</tbody></table></div></section>';
    }

    private function requirementsCard(): string
    {
        $screens = [
            __('All CoreX admin screens', 'corex'),
            __('Change operations mode', 'corex'),
            __('Prune submission data', 'corex'),
            __('Export submissions / data', 'corex'),
        ];

        $rows = '';
        foreach ($screens as $screen) {
            $rows .= '<li><span>' . esc_html($screen) . '</span><code>manage_options</code></li>';
        }

        return '<section class="corex-surface corex-access__card">'
            . '<header class="corex-access__head"><h2>' . esc_html__('Screen access requirements', 'corex') . '</h2></header>'
            . '<p class="corex-access__muted">'
            . esc_html__('CoreX admin and every dangerous action require the WordPress manage_options capability (typically Administrators).', 'corex')
            . '</p><ul class="corex-access__reqs">' . $rows . '</ul></section>';
    }

    private function deferralNote(): string
    {
        return '<div class="corex-access__note corex-surface">'
            . '<p class="corex-access__note-title">' . esc_html__('Editing roles', 'corex') . '</p>'
            . '<p class="corex-access__note-text">'
            . esc_html__(
                'This is a read-only baseline. A full capability/role editor and advanced access management are deliberately out of scope — CoreX never mutates roles here, so it cannot create a lockout. Use a dedicated roles plugin if you need to change capabilities.',
                'corex',
            )
            . '</p></div>';
    }

    /**
     * The real WordPress roles + their capabilities.
     *
     * @return list<array{key:string,name:string,caps:array<string,bool>}>
     */
    private function roles(): array
    {
        $roles = function_exists('wp_roles') ? wp_roles() : null;
        if (! $roles instanceof \WP_Roles) {
            return [];
        }

        $out = [];
        foreach ($roles->roles as $key => $role) {
            $out[] = [
                'key'  => (string) $key,
                'name' => translate_user_role((string) ($role['name'] ?? $key)),
                'caps' => array_map('boolval', (array) ($role['capabilities'] ?? [])),
            ];
        }

        return $out;
    }

    /**
     * @return array<string,bool>
     */
    private function currentUserCaps(): array
    {
        $user = wp_get_current_user();

        return array_map('boolval', (array) ($user->allcaps ?? []));
    }
}
