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

            usort($options, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

            return array_values($options);
        } catch (Throwable) {
            return [];
        }
    }
}
