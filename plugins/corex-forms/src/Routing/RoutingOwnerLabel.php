<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\Flow;

/**
 * A routing target in the words a site owner reads (spec 122, FR-015): "Sara Ahmed",
 * "Team: Sales", "Form owner (Sara Ahmed)", "Page author". Never a stored key.
 */
final readonly class RoutingOwnerLabel
{
    public function __construct(
        private RoutingDirectory $directory,
        private RoutingTargetRegistry $extensions,
    ) {
    }

    /**
     * @param array<string,mixed>       $target A stored target: `type` and `config`.
     * @param list<array<string,mixed>> $schema The flow's fields, to name a question by its label.
     */
    public function describe(array $target, Flow $flow, array $schema = []): string
    {
        $config = (array) ($target['config'] ?? []);
        $value = is_scalar($config['value'] ?? null) ? (string) $config['value'] : '';

        return match ((string) ($target['type'] ?? '')) {
            'user' => $this->directory->person((int) $value)?->name ?? __('A former user', 'corex'),
            /* translators: %s: a team's name. */
            'team' => sprintf(__('Team: %s', 'corex'), $this->directory->team($value)?->name ?? $value),
            /* translators: %s: a user role's name. */
            'role' => sprintf(__('Role: %s', 'corex'), $this->directory->roleName($value) ?? $value),
            'flow_owner' => sprintf(
                /* translators: %s: the name of the person who owns the form. */
                __('Form owner (%s)', 'corex'),
                $this->directory->person($flow->ownerId)?->name ?? __('a former user', 'corex'),
            ),
            'page_owner', 'post_author' => __('Page author', 'corex'),
            /* translators: %s: the label of the question whose answer chooses the owner. */
            'field_value' => sprintf(__('By the answer to "%s"', 'corex'), $this->question($value, $schema)),
            'extension' => $this->extensions->has($value) ? $this->extensions->label($value) : __('An extension that is not available', 'corex'),
            '' => __('No fallback', 'corex'),
            default => __('A target CoreX no longer offers', 'corex'),
        };
    }

    /** @param list<array<string,mixed>> $schema */
    private function question(string $key, array $schema): string
    {
        foreach ($schema as $field) {
            if (($field['key'] ?? '') === $key && trim((string) ($field['label'] ?? '')) !== '') {
                return (string) $field['label'];
            }
        }

        return $key;
    }
}
