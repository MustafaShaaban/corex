<?php

/**
 * The parity tree, in PHP (spec 136, "The parity contract", FR-011; DECISIONS #671).
 *
 * Turns markup into the tree the contract compares: every element that carries a class of the
 * block, with its tag, those classes sorted, and its nesting. A class of the block is the prefix
 * itself or the prefix followed by `__` or `--`. An element with no such class is transparent:
 * its children take its place. An element marked `data-corex-editor-only` is skipped with
 * everything inside it, and may not carry a class of the block. An element that carries a class
 * named in `pageOnly` is removed with everything inside it.
 *
 * The JavaScript twin is `packages/build-tools/parity/tree.js`; the two must compute the same tree.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use DOMDocument;
use DOMElement;

final class ParityTree
{
    public const EDITOR_ONLY = 'data-corex-editor-only';

    /**
     * Elements that are void in HTML and that libxml's HTML parser does not know as void: given
     * no closing tag, it would nest what follows inside them.
     */
    private const VOID_UNKNOWN_TO_LIBXML = 'source|track|wbr';

    /**
     * @param list<string> $pageOnly classes of elements only the page draws
     * @return array{tree: list<array<string,mixed>>, errors: list<string>}
     */
    public static function fromMarkup(string $markup, string $prefix, array $pageOnly = []): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . self::closeVoids($markup) . '</body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $errors = [];
        $body = $document->getElementsByTagName('body')->item(0);
        $tree = $body instanceof DOMElement ? self::nodesOf($body, $prefix, $pageOnly, $errors) : [];

        return ['tree' => $tree, 'errors' => $errors];
    }

    /**
     * A fixture's tree in the shape `fromMarkup()` returns: keys in order, tags in lower case,
     * classes sorted, no empty `children`.
     *
     * @param mixed $tree the tree as written in a `parity.json`
     * @return list<array<string,mixed>>
     */
    public static function normalize(mixed $tree): array
    {
        $normal = [];
        foreach (is_array($tree) ? $tree : [] as $node) {
            $node = is_array($node) ? $node : [];
            $classes = array_map('strval', is_array($node['classes'] ?? null) ? $node['classes'] : []);
            sort($classes, SORT_STRING);
            $entry = ['tag' => strtolower((string) ($node['tag'] ?? '')), 'classes' => $classes];
            $children = self::normalize($node['children'] ?? []);
            if ($children !== []) {
                $entry['children'] = $children;
            }
            $normal[] = $entry;
        }

        return $normal;
    }

    /**
     * A tree as an outline a person reads in a failure: one element per line, indented by its
     * depth, as `tag.class.class`; `(empty)` for an empty tree.
     *
     * @param list<array<string,mixed>> $tree
     */
    public static function format(array $tree, int $depth = 0): string
    {
        if ($depth === 0 && $tree === []) {
            return '(empty)';
        }

        $lines = [];
        foreach ($tree as $node) {
            $classes = array_map(static fn (string $class): string => '.' . $class, $node['classes']);
            $lines[] = str_repeat('  ', $depth) . $node['tag'] . implode('', $classes);
            if (isset($node['children'])) {
                $lines[] = self::format($node['children'], $depth + 1);
            }
        }

        return implode("\n", $lines);
    }

    public static function isBlockClass(string $name, string $prefix): bool
    {
        return $name === $prefix || str_starts_with($name, $prefix . '__') || str_starts_with($name, $prefix . '--');
    }

    private static function closeVoids(string $markup): string
    {
        return (string) preg_replace(
            '#<(' . self::VOID_UNKNOWN_TO_LIBXML . ')\b([^>]*?)/?>#i',
            '<$1$2></$1>',
            $markup,
        );
    }

    /**
     * @param list<string> $pageOnly
     * @param list<string> $errors
     * @return list<array<string,mixed>>
     */
    private static function nodesOf(DOMElement $parent, string $prefix, array $pageOnly, array &$errors): array
    {
        $nodes = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                array_push($nodes, ...self::nodeFor($child, $prefix, $pageOnly, $errors));
            }
        }

        return $nodes;
    }

    /**
     * @param list<string> $pageOnly
     * @param list<string> $errors
     * @return list<array<string,mixed>>
     */
    private static function nodeFor(DOMElement $element, string $prefix, array $pageOnly, array &$errors): array
    {
        if ($element->hasAttribute(self::EDITOR_ONLY)) {
            array_push($errors, ...self::editorOnlyErrors($element, $prefix));

            return [];
        }

        if (array_intersect(self::classesOf($element), $pageOnly) !== []) {
            return [];
        }

        $children = self::nodesOf($element, $prefix, $pageOnly, $errors);
        $classes = self::blockClassesOf($element, $prefix);
        if ($classes === []) {
            return $children;
        }

        $node = ['tag' => strtolower($element->tagName), 'classes' => $classes];
        if ($children !== []) {
            $node['children'] = $children;
        }

        return [$node];
    }

    /** @return list<string> */
    private static function editorOnlyErrors(DOMElement $element, string $prefix): array
    {
        $errors = [];
        $elements = [$element];
        foreach ($element->getElementsByTagName('*') as $inside) {
            $elements[] = $inside;
        }

        foreach ($elements as $node) {
            foreach (self::blockClassesOf($node, $prefix) as $class) {
                $errors[] = sprintf(
                    '<%s> is editor-only (%s) and carries the block class %s',
                    strtolower($node->tagName),
                    self::EDITOR_ONLY,
                    $class,
                );
            }
        }

        return $errors;
    }

    /** @return list<string> */
    private static function classesOf(DOMElement $element): array
    {
        return array_values(array_filter(preg_split('/\s+/', $element->getAttribute('class')) ?: [], 'strlen'));
    }

    /** @return list<string> */
    private static function blockClassesOf(DOMElement $element, string $prefix): array
    {
        $classes = array_values(array_unique(array_filter(
            self::classesOf($element),
            static fn (string $class): bool => self::isBlockClass($class, $prefix),
        )));
        sort($classes, SORT_STRING);

        return $classes;
    }
}
