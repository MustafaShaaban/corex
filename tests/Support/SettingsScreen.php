<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Boot;
use Corex\Config\Settings\ProviderSetup;
use Corex\Config\Settings\SettingsPageRenderer;
use Corex\Config\Settings\SettingsPages;
use Corex\Config\Settings\SettingsSaver;
use Corex\Config\Settings\SettingsSaveResult;
use Corex\Config\Settings\SettingsSectionStates;
use Corex\Multisite\ActivationScope;
use Corex\Multisite\PluginActivationInspector;

/**
 * A Settings page on real WordPress, drawn and saved as the screen does it, with the add-ons that
 * are on chosen per test (spec 119).
 *
 * `$active` names the add-on plugin files that are on; null leaves the site as it is.
 */
final class SettingsScreen
{
    /** Every add-on a Settings page belongs to. */
    public const ALL_ADDONS = ['corex-media/corex-media.php', 'corex-captcha/corex-captcha.php', 'corex-guides/corex-guides.php'];

    /**
     * @param list<string>|null $active
     */
    public static function states(?array $active = null): SettingsSectionStates
    {
        $container = Boot::app()->container();
        if ($active === null) {
            return $container->make(SettingsSectionStates::class);
        }

        return new SettingsSectionStates(
            $container->make(SettingsPages::class),
            self::inspector($active),
            $container->make(ProviderSetup::class),
        );
    }

    /**
     * The page's form and what the save said, as markup.
     *
     * @param list<string>|null $active
     */
    public static function draw(string $page, ?SettingsSaveResult $result = null, ?array $active = null): string
    {
        $container = Boot::app()->container();
        $renderer  = $container->make(SettingsPageRenderer::class, ['states' => self::states($active)]);
        $pages     = $container->make(SettingsPages::class);

        return $renderer->render(
            $pages->find($page) ?? throw new \InvalidArgumentException('No Settings page ' . $page),
            $pages->url($page),
            wp_nonce_field('corex_settings', 'corex_settings_nonce', true, false),
            $result,
        );
    }

    /**
     * Saves the page as a browser posts it: every editable field with the value it shows, and
     * the changes on top. `''` unticks a switch.
     *
     * @param array<string,string|list<string>> $changes field name => value
     * @param list<string>|null                 $active
     */
    public static function save(string $page, array $changes, ?array $active = null): ?SettingsSaveResult
    {
        $posted = self::posted(self::draw($page, null, $active), $changes);

        return Boot::app()->container()
            ->make(SettingsSaver::class, ['states' => self::states($active)])
            ->save($page, $posted, get_current_user_id());
    }

    /**
     * What a browser posts for a drawn form: enabled, editable fields with what they show.
     *
     * @param array<string,string|list<string>> $changes
     *
     * @return array<string,mixed>
     */
    public static function posted(string $html, array $changes): array
    {
        $posted = [];
        preg_match_all('/<input id="([a-z0-9_]+)" name="\1" type="(\w+)" value="([^"]*)"([^>]*)\/>/', $html, $inputs, PREG_SET_ORDER);
        foreach ($inputs as [, $name, $type, $value, $rest]) {
            if (str_contains($rest, 'disabled')) {
                continue;
            }
            if ($type === 'checkbox' && ! str_contains($rest, 'checked')) {
                continue;
            }
            $posted[$name] = html_entity_decode($value);
        }
        preg_match_all('/<select id="([a-z0-9_]+)" name="\1"([^>]*)>(.*?)<\/select>/s', $html, $selects, PREG_SET_ORDER);
        foreach ($selects as [, $name, $attributes, $options]) {
            if (str_contains($attributes, 'disabled')) {
                continue;
            }
            preg_match('/<option value="([^"]*)" selected/', $options, $selected);
            preg_match('/<option value="([^"]*)"/', $options, $first);
            $posted[$name] = html_entity_decode($selected[1] ?? $first[1] ?? '');
        }

        foreach ($changes as $name => $value) {
            if ($value === '') {
                unset($posted[$name]);
            } else {
                $posted[$name] = $value;
            }
        }

        return $posted;
    }

    /**
     * @param list<string> $active
     */
    public static function inspector(array $active): PluginActivationInspector
    {
        return new class ($active) implements PluginActivationInspector {
            /** @param list<string> $active */
            public function __construct(private readonly array $active)
            {
            }

            public function scopeOf(string $pluginFile): ActivationScope
            {
                return in_array($pluginFile, $this->active, true) ? ActivationScope::Site : ActivationScope::None;
            }

            public function isActive(string $pluginFile): bool
            {
                return $this->scopeOf($pluginFile)->isActive();
            }

            public function activePluginFiles(): array
            {
                return $this->active;
            }

            public function scopes(): array
            {
                return array_fill_keys($this->active, ActivationScope::Site);
            }
        };
    }
}
