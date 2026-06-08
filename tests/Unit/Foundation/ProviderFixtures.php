<?php

/**
 * Provider fixtures for the boot-lifecycle tests. Required directly (several
 * classes in one file) and ignored by Pest as a non-test file.
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

namespace Corex\Tests\Fixtures\Providers;

use Corex\Foundation\ServiceProvider;
use Corex\Tests\Fixtures\Hooks\ActionSubscriber;
use RuntimeException;

require_once __DIR__ . '/HookFixtures.php';

final class Recorder
{
    /**
     * @var list<string>
     */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }
}

final class ProviderA extends ServiceProvider
{
    public function register(): void
    {
        Recorder::$calls[] = 'A::register';
    }

    public function boot(): void
    {
        Recorder::$calls[] = 'A::boot';
    }
}

final class ProviderB extends ServiceProvider
{
    public function register(): void
    {
        Recorder::$calls[] = 'B::register';
    }

    public function boot(): void
    {
        Recorder::$calls[] = 'B::boot';
    }
}

final class ThrowingProvider extends ServiceProvider
{
    public function register(): void
    {
        throw new RuntimeException('register boom');
    }
}

final class SubscribingProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function subscribers(): array
    {
        return [ActionSubscriber::class];
    }
}

final class DiscoveringProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function controllerPaths(): array
    {
        return ['Corex\\Tests\\Fixtures\\Controllers\\' => dirname(__DIR__, 2) . '/Fixtures/Controllers'];
    }
}
