<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

use Corex\Cli\Generators\GeneratorContext;

/**
 * Finds the client plugin the `make:*` generators should write into.
 *
 * A client repository keeps its site under `sites/<client>/`, with the plugin `make:site` generated
 * at `<slug>-site/`. When there is exactly one, generated code belongs in its `src/`, under its
 * namespace. With none (the framework's own repository) or several there is nothing to choose by,
 * and the caller falls back to configuration.
 */
final class ClientSitePlugin
{
    private const PROVIDER_SUFFIX = 'ServiceProvider.php';

    /**
     * @param string $repositoryRoot The directory that holds `sites/`.
     */
    public function generatorContext(string $repositoryRoot): ?GeneratorContext
    {
        $providers = glob(rtrim($repositoryRoot, '/\\') . '/sites/*/*-site/src/*' . self::PROVIDER_SUFFIX) ?: [];

        if (count($providers) !== 1) {
            return null;
        }

        $source = dirname($providers[0]);

        return new GeneratorContext(
            $source,
            basename($providers[0], self::PROVIDER_SUFFIX),
            basename(dirname($source)),
        );
    }
}
