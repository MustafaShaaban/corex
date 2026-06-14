<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

use Corex\Cli\Support\Naming;

/**
 * Scaffolds a complete, secured REST resource from one name (spec 046): a thin controller,
 * its route registration (declaring the spec-005 middleware + a permission callback), a
 * request-validation shape, a response resource (output DTO), and a test — under the app's
 * `<base>/Api/` dir + namespace, answering with the spec-043 envelope. WP-CLI-independent
 * and headless-testable: it only renders stubs and writes files (no WordPress).
 *
 * Render-all-before-write: an unresolved placeholder fails loudly without leaving a
 * half-written resource on disk (mirrors BlockScaffolder).
 */
final class ApiResourceScaffolder
{
    public function __construct(
        private readonly StubRenderer $renderer,
        private readonly Naming $naming,
        private readonly string $stubsDir,
    ) {
    }

    public function scaffold(string $rawName, GeneratorContext $context, bool $force = false): ApiResourceScaffoldResult
    {
        $base         = $this->naming->classNameFor($rawName);
        $appNamespace = $context->namespace . '\\Api';

        $values = [
            'name'             => $base,
            'app_namespace'    => $appNamespace,
            'controller_class' => $base . 'Controller',
            'routes_class'     => $base . 'Routes',
            'request_class'    => $base . 'Request',
            'resource_class'   => $base . 'Resource',
            'service'          => $base . 'Service',
            'service_fqcn'     => $context->namespace . '\\Services\\' . $base . 'Service',
            'rest_namespace'   => $context->prefix . '/v1',
            'route'            => strtolower($base),
            'text_domain'      => $context->prefix,
        ];

        $apiDir = $this->join($context->basePath, 'Api');

        $files = [
            $apiDir . DIRECTORY_SEPARATOR . $base . 'Controller.php' => 'api-resource/controller',
            $apiDir . DIRECTORY_SEPARATOR . $base . 'Routes.php'     => 'api-resource/routes',
            $apiDir . DIRECTORY_SEPARATOR . $base . 'Request.php'    => 'api-resource/request',
            $apiDir . DIRECTORY_SEPARATOR . $base . 'Resource.php'   => 'api-resource/resource',
            $apiDir . DIRECTORY_SEPARATOR . $base . 'Test.php'       => 'api-resource/test',
        ];

        $controllerPath = $apiDir . DIRECTORY_SEPARATOR . $base . 'Controller.php';
        if (is_file($controllerPath) && ! $force) {
            return ApiResourceScaffoldResult::skipped($apiDir);
        }

        $rendered = [];
        foreach ($files as $path => $stub) {
            $rendered[$path] = $this->renderer->render($this->readStub($stub), $values);
        }

        return $this->writeAll($apiDir, $rendered);
    }

    /**
     * @param array<string,string> $rendered path => contents
     */
    private function writeAll(string $apiDir, array $rendered): ApiResourceScaffoldResult
    {
        $written = [];

        foreach ($rendered as $path => $contents) {
            $dir = dirname($path);

            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return ApiResourceScaffoldResult::error($apiDir, sprintf('Could not create directory: %s', $dir));
            }

            if (file_put_contents($path, $contents) === false) {
                return ApiResourceScaffoldResult::error($apiDir, sprintf('Could not write: %s', $path));
            }

            $written[] = $path;
        }

        return ApiResourceScaffoldResult::created($apiDir, $written);
    }

    private function readStub(string $name): string
    {
        return (string) file_get_contents(
            rtrim($this->stubsDir, '/\\') . DIRECTORY_SEPARATOR . $name . '.stub'
        );
    }

    private function join(string ...$parts): string
    {
        return rtrim($parts[0], '/\\') . DIRECTORY_SEPARATOR
            . implode(DIRECTORY_SEPARATOR, array_slice($parts, 1));
    }
}
