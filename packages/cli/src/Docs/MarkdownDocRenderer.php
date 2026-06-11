<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Docs;

defined('ABSPATH') || exit;

/**
 * Renders a ClassDoc to a Starlight Markdown page: YAML frontmatter (title +
 * description) followed by the kind/FQCN, the class summary, and a "Public API"
 * list of method signatures with their summaries. Pure — no filesystem, no WP.
 */
final class MarkdownDocRenderer
{
    public function render(ClassDoc $doc, string $layer): string
    {
        $description = $this->frontmatterValue(
            $doc->summary !== '' ? $doc->summary : sprintf('%s %s', $doc->shortName, $doc->kind)
        );

        $body = sprintf(
            "---\ntitle: %s\ndescription: %s\n---\n\n",
            $this->frontmatterValue($doc->shortName),
            $description,
        );

        $body .= sprintf("`%s` · %s · *%s layer*\n\n", $doc->fqcn, $doc->kind, $layer);

        if ($doc->summary !== '') {
            $body .= $doc->summary . "\n\n";
        }

        $body .= "## Public API\n\n";

        if ($doc->methods === []) {
            $body .= "_No public methods._\n";

            return $body;
        }

        foreach ($doc->methods as $method) {
            $body .= sprintf("### `%s`\n\n", $method['signature']);

            if ($method['summary'] !== '') {
                $body .= $method['summary'] . "\n\n";
            }
        }

        return $body;
    }

    /**
     * A YAML-safe double-quoted scalar: one line, internal quotes/backslashes
     * escaped, trimmed to a sensible length so the frontmatter never breaks.
     */
    private function frontmatterValue(string $value): string
    {
        $oneLine = trim((string) preg_replace('/\s+/', ' ', $value));

        if (strlen($oneLine) > 160) {
            $oneLine = rtrim(substr($oneLine, 0, 157)) . '…';
        }

        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $oneLine);

        return '"' . $escaped . '"';
    }
}
