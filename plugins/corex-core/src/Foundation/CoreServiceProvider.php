<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Support\BootLogger;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\Repository;
use Corex\Support\Config\Sources\DefaultsSource;
use Corex\Support\Config\Sources\DotenvSource;
use Corex\Support\Config\Sources\OptionsSource;

/**
 * The foundation's own provider. Binds the layered config engine
 * (.env → WP options → defaults) as a shared service.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ConfigInterface::class, function (ContainerInterface $container): Repository {
            return new Repository([
                new DotenvSource($this->projectRoot(), $container->make(BootLogger::class)),
                new OptionsSource(),
                new DefaultsSource($this->defaults()),
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    public function controllerPaths(): array
    {
        return ['Corex\\Controllers\\' => COREX_CORE_PATH . 'src/Controllers'];
    }

    /**
     * Aggregate every shipped config file as `basename => contents`
     * (config/app.php → 'app', config/query.php → 'query', …).
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        $defaults = [];

        foreach (glob(COREX_CORE_PATH . 'config/*.php') ?: [] as $file) {
            $defaults[basename($file, '.php')] = require $file;
        }

        return $defaults;
    }

    private function projectRoot(): string
    {
        return dirname(COREX_CORE_PATH, 2);
    }
}
