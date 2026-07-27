<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Forms;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Forms\Catalog\FormCatalog;
use Corex\Forms\Catalog\FormCatalogEntry;
use Throwable;

/**
 * The list of forms a person can filter by, by name.
 *
 * Submissions asked for a raw numeric flow ID and Records for a typed slug — neither of which
 * anyone knows or should have to. This supplies the real names for both screens.
 *
 * It reads the {@see FormCatalog}, so a form registered in code appears here the moment it is
 * registered. It used to read the flow table alone, which meant the framework's own `contact` form
 * — a code form — could receive submissions the filter could not narrow to, and every site had to
 * patch a CoreX filter to list its own forms.
 *
 * Forms is an optional add-on (Principle IX), so the catalog is resolved lazily inside a try/catch
 * and an absent one yields an empty list rather than a fatal — the screens still work, they just
 * cannot offer the filter.
 */
final class FlowFilterOptions
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * Every form, as the two screens need to key on it.
     *
     * Both id and slug are returned because the screens filter on different stored keys — the
     * submissions inbox matches `corex_flow_id`, the data explorer matches `corex_form_slug` —
     * and conflating them yields a filter that silently matches nothing. A form with no flow row
     * carries `id => 0`, which both screens read as "match this by slug instead".
     *
     * @return list<array{id:int,name:string,slug:string}>
     */
    public function all(): array
    {
        try {
            /** @var FormCatalog $catalog */
            $catalog = $this->container->make(FormCatalog::class);

            $options = array_map(static fn (FormCatalogEntry $entry): array => [
                'id' => $entry->flowId ?? 0,
                'name' => $entry->label,
                'slug' => $entry->slug,
            ], $catalog->all());

            /**
             * Filters the forms offered by the submissions and records filters.
             *
             * Discovery is now the framework's job — visual flows, `Corex\Forms\FormRegistry`
             * forms, and anything a `FormCatalogProvider` contributes are already here. This
             * filter remains for the cases the catalog cannot see, and for backward compatibility
             * with sites that added entries before the catalog existed. It is applied *after* the
             * merge, so an entry it adds for a form CoreX already found is deduplicated by slug
             * rather than listed twice. Use `id => 0` to say "there is no flow row; match this by
             * `corex_form_slug` instead".
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
     * Force injected entries into the shape both screens rely on, and keep one row per form.
     *
     * A filter is an open door: anything can come back through it. The screens key on `id` and
     * `slug` and render `name`, so an entry missing one of those would render a nameless row or a
     * filter that silently matches nothing. Entries without a usable slug and without a flow id
     * cannot be matched by either screen, so they are dropped rather than shown.
     *
     * Deduplication is by slug, first entry winning, because the catalog runs before the filter:
     * a site that still injects its own copy of a form CoreX now discovers gets one row, not two,
     * and the catalog's richer version is the one that survives.
     *
     * @param array<mixed> $options
     * @return list<array{id:int,name:string,slug:string}>
     */
    private static function normalize(array $options): array
    {
        $clean = [];
        $seen  = [];

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

            // A slugless flow can only be keyed by its id; give it a distinct dedup key so two
            // different flows cannot collapse into one row.
            $key = $slug !== '' ? 'slug:' . $slug : 'id:' . $id;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $clean[]    = [
                'id' => max(0, $id),
                'name' => $name !== '' ? $name : $slug,
                'slug' => $slug,
            ];
        }

        return $clean;
    }
}
