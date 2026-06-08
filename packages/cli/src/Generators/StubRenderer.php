<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * Renders a stub by substituting `{{ token }}` placeholders. Any placeholder left
 * unresolved after substitution is an error, so a stub/value mismatch fails loudly
 * instead of writing a broken file (spec FR-001, FR-003).
 */
final class StubRenderer
{
    /**
     * @param array<string, string> $values
     */
    public function render(string $stub, array $values): string
    {
        $rendered = $stub;

        foreach ($values as $key => $value) {
            $rendered = str_replace('{{ ' . $key . ' }}', $value, $rendered);
        }

        if (preg_match('/\{\{\s*[\w.]+\s*\}\}/', $rendered, $matches) === 1) {
            throw new UnresolvedPlaceholderException(
                sprintf('Unresolved stub placeholder: %s', trim($matches[0]))
            );
        }

        return $rendered;
    }
}
