<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use JsonException;

/**
 * Immutable configuration captured by a flow version.
 */
final readonly class FlowConfiguration
{
    /**
     * Named arrays mirror the versioned persistence document and are not collaborators.
     *
     * @param list<array<string,mixed>> $schema
     * @param array<string,mixed>       $validation
     * @param array<string,mixed>       $routing
     * @param array<string,mixed>       $emailRoutes
     * @param array<string,mixed>       $success
     * @param array<string,mixed>       $placementSnapshot
     * @param array<string,mixed>       $protection Spam-protection expectations: captcha (inherit|on|off),
     *                                              action (?string), threshold (?float). Empty = inherit all.
     */
    public function __construct(
        public array $schema,
        public array $validation,
        public array $routing,
        public array $emailRoutes,
        public array $success,
        public array $placementSnapshot,
        public array $protection = [],
    ) {
    }

    /** @throws JsonException */
    public function checksum(): string
    {
        $document = [
            'schema' => $this->schema,
            'validation' => $this->validation,
            'routing' => $this->routing,
            'email_routes' => $this->emailRoutes,
            'success' => $this->success,
            'placement_snapshot' => $this->placementSnapshot,
        ];

        // Only include protection once a form actually declares it, so every version published
        // before this field existed keeps the exact checksum it already stored. A new key in the
        // canonical document would otherwise re-hash every version on every live site at once.
        if ($this->protection !== []) {
            $document['protection'] = $this->protection;
        }

        return hash('sha256', json_encode($this->canonical($document), JSON_THROW_ON_ERROR));
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $entry) {
            $value[$key] = $this->canonical($entry);
        }

        return $value;
    }
}
