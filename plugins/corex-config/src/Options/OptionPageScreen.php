<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Options;

defined('ABSPATH') || exit;

use Corex\Config\Settings\SettingsForm;
use Corex\Config\Settings\SettingsStore;

/**
 * Renders + persists every registered {@see OptionPage}. For each page it adds the admin menu (top
 * level or a submenu of its parent), renders the form with the shared spec-032 `SettingsForm`
 * controls, and saves on submit — verifying the page's **capability** and a **per-page nonce**, and
 * sanitising each value by its field type (Principle VII). A thin boundary: the page metadata +
 * form rendering are the unit-tested pure pieces (spec 039).
 */
final class OptionPageScreen
{
    public function __construct(
        private readonly OptionPageRegistry $registry,
        private readonly SettingsStore $store,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'maybeSave']);
    }

    public function menu(): void
    {
        foreach ($this->registry->all() as $page) {
            $slug     = 'corex-page-' . $page->slug();
            $callback = function () use ($page): void {
                $this->render($page);
            };

            if ($page->parent() === '') {
                add_menu_page($page->title(), $page->menuLabel(), $page->capability(), $slug, $callback);
            } else {
                add_submenu_page($page->parent(), $page->title(), $page->menuLabel(), $page->capability(), $slug, $callback);
            }
        }
    }

    public function render(OptionPage $page): void
    {
        if (! current_user_can($page->capability())) {
            return;
        }

        $nonce = wp_nonce_field('corex_optionpage_' . $page->slug(), 'corex_optionpage_nonce', true, false)
            . sprintf('<input type="hidden" name="corex_optionpage" value="%s" />', esc_attr($page->slug()));

        echo '<div class="wrap"><h1>' . esc_html($page->title()) . '</h1>'
            . (new SettingsForm($page))->render(fn (string $key): string => $this->store->get($key), $nonce)
            . '</div>'; // SettingsForm escapes every field per type.
    }

    public function maybeSave(): void
    {
        if (empty($_POST['corex_optionpage'])) {
            return;
        }

        $slug = sanitize_key(wp_unslash($_POST['corex_optionpage']));
        $page = $this->registry->find($slug);

        if ($page === null || ! current_user_can($page->capability())) {
            return;
        }

        if (! check_admin_referer('corex_optionpage_' . $slug, 'corex_optionpage_nonce')) {
            return;
        }

        foreach ($page->sections() as $section) {
            foreach ($section['fields'] as $key => $field) {
                $name = str_replace('.', '_', $key);

                if (! isset($_POST[$name])) {
                    if ($field['type'] === 'checkbox') {
                        $this->store->save($key, '');
                    }

                    continue;
                }

                $this->store->save($key, $this->sanitize($field['type'], wp_unslash($_POST[$name])));
            }
        }

        wp_safe_redirect(add_query_arg('updated', '1', wp_get_referer() ?: admin_url()));
        exit;
    }

    /**
     * @param mixed $value
     */
    private function sanitize(string $type, $value): string
    {
        return match ($type) {
            'email'         => sanitize_email((string) $value),
            'url', 'media'  => esc_url_raw((string) $value),
            'checkbox'      => $value ? '1' : '',
            default         => sanitize_text_field((string) $value),
        };
    }
}
