<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

use Corex\Cli\Support\InvalidNameException;

/**
 * The derived identity of a client site (spec 049): from one name, all the namespaces and
 * prefixes a generated site uses — guaranteed **distinct from Corex's own** (`Corex\`,
 * `corex/v1`, `--corex-`, `corex_`). Pure + immutable; a name normalising to empty or
 * `corex` is refused so the client and framework can never collide.
 */
final class SiteIdentity
{
    private function __construct(
        public readonly string $name,
        public readonly string $namespace,
        public readonly string $pluginSlug,
        public readonly string $themeSlug,
        public readonly string $textDomain,
        public readonly string $restNamespace,
        public readonly string $cssPrefix,
        public readonly string $optionPrefix,
    ) {
    }

    public static function from(string $rawName): self
    {
        $slug = self::slugify($rawName);

        if ($slug === '' || $slug === 'corex') {
            throw new InvalidNameException(sprintf('Invalid or reserved site name: "%s".', $rawName));
        }

        $studly = self::studly($slug);

        return new self(
            $studly,
            $studly . 'Site',
            $slug . '-site',
            $slug,
            $slug . '-site',
            $slug . '/v1',
            '--' . $slug . '-',
            str_replace('-', '_', $slug) . '_',
        );
    }

    private static function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }

    private static function studly(string $slug): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug)));
    }
}
