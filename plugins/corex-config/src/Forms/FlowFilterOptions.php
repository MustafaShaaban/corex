<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Forms;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowRepository;
use Throwable;

/**
 * The list of forms a person can filter by, by name.
 *
 * Submissions asked for a raw numeric flow ID and Records for a typed slug — neither of which
 * anyone knows or should have to. This supplies the real names for both screens.
 *
 * Forms is an optional add-on (Principle IX), so it is resolved lazily inside a try/catch and an
 * absent one yields an empty list rather than a fatal — the screens still work, they just cannot
 * offer the filter. Same shape as InsightWidgetFacts uses for the same reason.
 */
final class FlowFilterOptions
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * Every flow, as the two screens need to key on it.
     *
     * Both id and slug are returned because the screens filter on different stored keys — the
     * submissions inbox matches `corex_flow_id`, the data explorer matches `corex_form_slug` —
     * and conflating them yields a filter that silently matches nothing.
     *
     * @return list<array{id:int,name:string,slug:string}>
     */
    public function all(): array
    {
        try {
            /** @var FlowRepository $flows */
            $flows = $this->container->make(FlowRepository::class);

            $options = array_map(static fn (Flow $flow): array => [
                'id' => $flow->id,
                // Fall back to the slug rather than render a nameless row.
                'name' => $flow->name !== '' ? $flow->name : $flow->slug,
                'slug' => $flow->slug,
            ], $flows->all());

            /**
             * Filters the forms offered by the submissions and records filters.
             *
             * Only builder flows live in the database, so a form registered in code through
             * `Corex\Forms\FormRegistry` has no row here and never appeared in the filter — its
             * submissions were listed but could not be narrowed to. Append entries with `id => 0`
             * to say "there is no flow row; match this by `corex_form_slug` instead".
             *
             * @param list<array{id:int,name:string,slug:string}> $options
             */
            $options = apply_filters('corex_submission_filter_options', $options);

            $options = self::normalize(is_array($options) ? $options : []);

            usort($options, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

            return array_values($options);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Force injected entries into the shape both screens rely on.
     *
     * A filter is an open door: anything can come back through it. The screens key on `id` and
     * `slug` and render `name`, so an entry missing one of those would render a nameless row or a
     * filter that silently matches nothing. Entries without a usable slug and without a flow id
     * cannot be matched by either screen, so they are dropped rather than shown.
     *
     * @param array<mixed> $options
     * @return list<array{id:int,name:string,slug:string}>
     */
    private static function normalize(array $options): array
    {
        $clean = [];

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $id   = (int) ($option['id'] ?? 0);
            $slug = sanitize_key((string) ($option['slug'] ?? ''));
            $name = trim((string) ($option['name'] ?? ''));

            if ($id < 1 && $slug === '') {
                continue;
            }

            $clean[] = [
                'id' => max(0, $id),
                'name' => $name !== '' ? $name : $slug,
                'slug' => $slug,
            ];
        }

        return $clean;
    }
}
