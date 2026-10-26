<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Abilities;

defined('ABSPATH') || exit;

use Corex\Foundation\ServiceProvider;
use WP_Block_Type_Registry;

/**
 * Registers Corex's WP 7.0 Abilities (the agent/MCP discovery surface), gated on the
 * Abilities API being present so the framework runs unchanged on older cores. The
 * abilities are read-only (annotated `readonly` and `idempotent`), capability-gated, and
 * exposed in REST. Registration happens on the API's own init hooks, as the API requires.
 *
 * They are public to MCP on purpose (spec 123, DECISIONS #500, #508). The MCP Adapter lists an
 * ability only when `meta.mcp.public` is true, or `meta.public` is true and `meta.mcp.public` is
 * unset; without the first key no agent could see these. `meta.public` is left unset because from
 * WordPress 7.1 it also opts an ability into every other client channel.
 */
final class AbilitiesProvider extends ServiceProvider
{
    private const CATEGORY = 'corex';

    public function register(): void
    {
        $this->container->singleton(CorexAbilities::class);
    }

    public function boot(): void
    {
        if (! function_exists('wp_register_ability')) {
            return; // Abilities API absent (WP < 7.0) — degrade silently.
        }

        add_action('wp_abilities_api_categories_init', [$this, 'registerCategory']);
        add_action('wp_abilities_api_init', [$this, 'registerAbilities']);
    }

    public function registerCategory(): void
    {
        wp_register_ability_category(self::CATEGORY, [
            'label'       => __('Corex', 'corex'),
            'description' => __('Read-only discovery of a Corex site.', 'corex'),
        ]);
    }

    public function registerAbilities(): void
    {
        $abilities = $this->container->make(CorexAbilities::class);

        $this->registerReadOnlyAbility(
            'corex/list-blocks',
            __('List Corex blocks', 'corex'),
            __('The registered corex/* blocks on this site.', 'corex'),
            ['type' => 'array'],
            static fn (): array => $abilities->blocks(
                WP_Block_Type_Registry::get_instance()->get_all_registered()
            ),
        );

        $this->registerReadOnlyAbility(
            'corex/site-info',
            __('Corex site info', 'corex'),
            __('A small read-only Corex/site summary.', 'corex'),
            ['type' => 'object'],
            static fn (): array => $abilities->siteInfo(
                (string) get_bloginfo('name'),
                defined('COREX_CORE_VERSION') ? (string) COREX_CORE_VERSION : 'dev',
                count(array_filter(
                    array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered()),
                    static fn (string $name): bool => str_starts_with($name, 'corex/')
                ))
            ),
        );
    }

    /**
     * Register one read-only, capability-gated, REST-exposed, MCP-public ability: the shared
     * shape of these two, so each call site only supplies what differs. They stay behind
     * `edit_posts` because `corex/site-info` carries the CoreX version, which spec 111 keeps
     * from anyone looking for a vulnerable release; `read` would show it to every subscriber
     * (DECISIONS #508).
     *
     * @param array<string,string> $outputSchema
     * @param callable():array<mixed> $execute
     */
    private function registerReadOnlyAbility(
        string $id,
        string $label,
        string $description,
        array $outputSchema,
        callable $execute,
    ): void {
        wp_register_ability($id, [
            'label'               => $label,
            'description'         => $description,
            'category'            => self::CATEGORY,
            'output_schema'       => $outputSchema,
            'execute_callback'    => $execute,
            'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
            'meta'                => [
                'annotations'  => ['readonly' => true, 'idempotent' => true],
                'show_in_rest' => true,
                'mcp'          => ['public' => true],
            ],
        ]);
    }
}
