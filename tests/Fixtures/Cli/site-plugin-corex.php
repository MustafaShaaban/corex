<?php

/**
 * The few CoreX classes a generated site plugin touches, faked for boot-site-plugin.php.
 *
 * `Corex::onReady` does what the real one does (plugins/corex-core/src/Support/Facades/Corex.php): it
 * runs the callback at once when CoreX has booted, and on `corex_booted` otherwise. The container
 * writes down what it is asked for.
 *
 * @package Corex\Tests\Fixtures\Cli
 */

declare(strict_types=1);

namespace Corex\Container {
    interface ContainerInterface
    {
        public function has(string $id): bool;

        public function make(string $id, array $parameters = []): mixed;

        public function singleton(string $id, \Closure|string|null $concrete = null): void;
    }

    final class FakeContainer implements ContainerInterface
    {
        /** @var list<string> */
        public array $made = [];

        /** @var list<string> */
        public array $singletons = [];

        public function __construct(private readonly bool $offersBlockFolder)
        {
        }

        public function has(string $id): bool
        {
            return $id === \Corex\Blocks\BlockFolder::class ? $this->offersBlockFolder : true;
        }

        public function make(string $id, array $parameters = []): mixed
        {
            $this->made[] = $id;

            if ($id === \Corex\Blocks\BlockFolder::class) {
                return new \Corex\Blocks\BlockFolder();
            }

            // Whatever the provider resolves and then registers: it only needs to answer.
            return new class () {
                public function __call(string $method, array $arguments): mixed
                {
                    return null;
                }
            };
        }

        public function singleton(string $id, \Closure|string|null $concrete = null): void
        {
            $this->singletons[] = $id;
        }
    }
}

namespace Corex\Blocks {
    final class BlockFolder
    {
        /** @var list<array{0: string, 1: string}> */
        public static array $calls = [];

        public function register(string $builtDir, string $sourceDir): void
        {
            self::$calls[] = [str_replace('\\', '/', $builtDir), str_replace('\\', '/', $sourceDir)];
        }
    }
}

namespace Corex\Foundation {
    use Corex\Container\ContainerInterface;

    final class Application
    {
        public function __construct(private readonly ContainerInterface $container)
        {
        }

        public function container(): ContainerInterface
        {
            return $this->container;
        }
    }
}

namespace Corex\Support\Facades {
    use Corex\Foundation\Application;

    final class Corex
    {
        public static ?Application $app = null;

        public static function onReady(callable $callback): void
        {
            if (self::$app !== null) {
                $callback(self::$app);

                return;
            }

            add_action('corex_booted', $callback, 10, 1);
        }
    }
}
