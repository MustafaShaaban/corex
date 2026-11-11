<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Support\BootLogger;
use Throwable;

/**
 * Fills every registered email variable into a flow email's context (spec 122, FR-023).
 *
 * A variable `crm.id` lands at `['crm']['id']`, where a template's `{{ crm.id }}` reads it. A
 * resolver that fails, or returns something that is not a single value, leaves the variable empty
 * and the failure is written to the log: one broken extension does not stop an email.
 */
final readonly class EmailVariableValues
{
    public function __construct(
        private EmailVariableRegistry $variables,
        private BootLogger $logger,
    ) {
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function into(array $context): array
    {
        foreach ($this->variables->definitions() as $definition) {
            $key = $definition['key'];
            try {
                $value = $this->variables->resolve($key, $context);
            } catch (Throwable $error) {
                $this->logger->warning(sprintf('CoreX email variable "%s" could not be resolved: %s', $key, $error->getMessage()));
                $value = '';
            }
            $context = $this->place($context, explode('.', $key), $value === null ? '' : $value);
        }

        return $context;
    }

    /**
     * @param array<string,mixed> $context
     * @param list<string>        $path
     * @return array<string,mixed>
     */
    private function place(array $context, array $path, string|int|float|bool $value): array
    {
        $segment = array_shift($path);
        if ($path === []) {
            $context[$segment] = $value;

            return $context;
        }
        $inner = isset($context[$segment]) && is_array($context[$segment]) ? $context[$segment] : [];
        $context[$segment] = $this->place($inner, $path, $value);

        return $context;
    }
}
