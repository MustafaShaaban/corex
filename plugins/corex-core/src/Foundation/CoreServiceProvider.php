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
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return require COREX_CORE_PATH . 'config/app.php';
    }

    private function projectRoot(): string
    {
        return dirname(COREX_CORE_PATH, 2);
    }
}
