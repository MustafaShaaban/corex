<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Email\Studio\EmailDeliveryContextFactory;
use Corex\Email\Studio\EmailStudioService;
use Corex\Environment\SiteEnvironment;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\Source;

/**
 * Email Studio's delivery state for a test that states the environment as `app.env` in a
 * configuration of its own: that value locks the environment, as `APP_ENV` would on a site.
 */
final class StudioDelivery
{
    public static function factory(ConfigInterface $config, EmailStudioService $studio): EmailDeliveryContextFactory
    {
        return new EmailDeliveryContextFactory(self::environment($config), $config, $studio);
    }

    public static function environment(ConfigInterface $config): SiteEnvironment
    {
        return new SiteEnvironment([[SiteEnvironment::BY_DOTENV, new class ($config) implements Source {
            public function __construct(private readonly ConfigInterface $config)
            {
            }

            public function has(string $key): bool
            {
                return $this->config->has($key);
            }

            public function get(string $key): mixed
            {
                return $this->config->get($key);
            }
        }]]);
    }
}
