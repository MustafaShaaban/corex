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
use RuntimeException;

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
