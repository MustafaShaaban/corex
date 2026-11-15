<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Forms\Routing\RoutingTargetRegistry;
use Corex\Support\BootLogger;
use InvalidArgumentException;

/**
 * Reads the three documented WordPress hooks into the flow registries (spec 122, FR-021).
 *
 * Each filter is handed an empty array and returns `key => ['label' => …, 'callback' => …]`:
 *
 * - `corex_flow_actions`: the callback receives the stored submission and runs after storage;
 * - `corex_flow_email_variables`: the callback receives the email's context and returns one value;
 * - `corex_flow_routing_targets`: the callback receives the submission and returns who it goes to.
 *
 * Read once, on `init`. An entry without a label or a callback, or with a key already taken, is
 * left out and named in the log; the others still register.
 */
final readonly class FlowExtensionHooks
{
    public function __construct(
        private FlowActionRegistry $actions,
        private EmailVariableRegistry $variables,
        private RoutingTargetRegistry $targets,
        private BootLogger $logger,
    ) {
    }

    public function register(): void
    {
        $this->read('corex_flow_actions', $this->actions->register(...));
        $this->read('corex_flow_email_variables', $this->variables->register(...));
        $this->read('corex_flow_routing_targets', $this->targets->register(...));
    }

    /** @param callable(string,string,callable):void $register */
    private function read(string $hook, callable $register): void
    {
        $entries = apply_filters($hook, []);
        foreach (is_array($entries) ? $entries : [] as $key => $entry) {
            $label = is_array($entry) ? ($entry['label'] ?? null) : null;
            $callback = is_array($entry) ? ($entry['callback'] ?? null) : null;
            if (! is_string($key) || ! is_string($label) || ! is_callable($callback)) {
                $this->logger->warning(sprintf('CoreX ignored an entry of %s: it needs a key, a label and a callback.', $hook));
                continue;
            }
            try {
                $register($key, $label, $callback);
            } catch (InvalidArgumentException $refused) {
                $this->logger->warning(sprintf('CoreX ignored "%s" from %s: %s', $key, $hook, $refused->getMessage()));
            }
        }
    }
}
