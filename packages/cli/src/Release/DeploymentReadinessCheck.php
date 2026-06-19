<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Builds deployment readiness findings from the supported profile matrix.
 */
final class DeploymentReadinessCheck
{
    /**
     * @return list<DeploymentProfile>
     */
    public static function defaultProfiles(): array
    {
        return [
            self::profile([
                'name' => 'minimal',
                'packageShape' => 'Framework source plus production vendor and built assets for a standard WordPress install.',
                'buildCommands' => ['composer install --no-dev --optimize-autoloader', 'npm ci', 'npm run build'],
                'dependencies' => ['PHP 8.3+', 'MySQL/MariaDB', 'WordPress 7.0+'],
                'secrets' => ['database credentials', 'WordPress auth salts'],
            ]),
            self::profile([
                'name' => 'standard',
                'packageShape' => 'Tagged Corex framework release deployed with active core plugins, theme, vendor, and assets.',
                'buildCommands' => ['composer validate --no-check-publish', 'composer test', 'npm run build'],
                'dependencies' => ['PHP 8.3+', 'Node 20+', 'WP-CLI', 'WordPress 7.0+'],
                'secrets' => ['database credentials', 'mail credentials', 'WordPress auth salts'],
            ]),
            self::profile([
                'name' => 'full',
                'packageShape' => 'Full first-party Corex runtime with optional add-ons enabled by Corex feature state.',
                'buildCommands' => ['composer test', 'npm run build', 'npm run test:js'],
                'dependencies' => ['PHP 8.3+', 'Node 20+', 'WP-CLI', 'optional add-on plugin files'],
                'secrets' => ['database credentials', 'mail credentials', 'captcha keys', 'WordPress auth salts'],
            ]),
            self::profile([
                'name' => 'woo',
                'packageShape' => 'Corex framework plus WooCommerce and the Woo kit when the external Woo dependency is installed.',
                'buildCommands' => ['composer test', 'npm run build', 'wp plugin is-installed woocommerce'],
                'dependencies' => ['PHP 8.3+', 'WooCommerce', 'WordPress 7.0+'],
                'secrets' => ['database credentials', 'payment provider keys', 'WordPress auth salts'],
            ]),
            self::profile([
                'name' => 'client-site',
                'packageShape' => 'Generated client plugin/theme repository consuming Corex as framework source or release package.',
                'buildCommands' => ['wp corex make:site Acme --path=dist/acme', 'wp corex compliance:check'],
                'dependencies' => ['Corex release package', 'PHP 8.3+', 'WordPress 7.0+'],
                'secrets' => ['client database credentials', 'client mail credentials', 'WordPress auth salts'],
            ]),
            self::profile([
                'name' => 'shared-host',
                'packageShape' => 'Flat WordPress tree with Corex copied into wp-content for hosts without symlink support.',
                'buildCommands' => [
                    'composer install --no-dev --optimize-autoloader',
                    'npm run build',
                    'assemble dist/ WordPress tree',
                ],
                'dependencies' => ['PHP 8.3 selector', 'MySQL via host panel', 'SFTP/FTP access'],
                'secrets' => ['database credentials', 'SFTP credentials', 'WordPress auth salts'],
                'blockers' => ['Host must verify PHP extensions, file permissions, and no-symlink upload shape.'],
            ]),
            self::profile([
                'name' => 'azure-container',
                'packageShape' => 'Production Docker image deployed to Azure App Service for Containers with managed MySQL.',
                'buildCommands' => ['docker build --target prod -t corex:prod .', 'az webapp config container set'],
                'dependencies' => ['Docker', 'Azure CLI', 'Azure Container Registry', 'Azure Database for MySQL'],
                'secrets' => ['Azure credentials', 'registry credentials', 'database credentials', 'Key Vault secrets'],
                'blockers' => ['Requires live Azure subscription and repository secret verification.'],
            ]),
            self::profile([
                'name' => 'local-docker',
                'packageShape' => 'Docker Compose development stack with the monorepo mounted into WordPress.',
                'buildCommands' => ['docker compose up -d --build', 'docker compose exec php composer test'],
                'dependencies' => ['Docker daemon', 'Docker Compose', 'local bind mounts'],
                'secrets' => ['local database password', 'WordPress local salts'],
                'blockers' => ['Requires Docker daemon availability on the developer machine.'],
            ]),
            self::profile([
                'name' => 'wp-env-stable',
                'packageShape' => 'wp-env stable WordPress target for plugin/theme smoke and browser checks.',
                'buildCommands' => ['npm run env:start', 'npm run test:e2e', 'npm run env:stop'],
                'dependencies' => ['Docker daemon', '@wordpress/env', 'WordPress stable image'],
                'secrets' => ['local wp-env credentials', 'WordPress local salts'],
                'blockers' => ['Requires Docker/wp-env availability.'],
            ]),
            self::profile([
                'name' => 'wp-env-trunk',
                'packageShape' => 'wp-env trunk WordPress compatibility target for future WordPress regression checks.',
                'buildCommands' => ['npm run env:start -- --update', 'npm run test:e2e', 'npm run env:stop'],
                'dependencies' => ['Docker daemon', '@wordpress/env', 'WordPress trunk image'],
                'secrets' => ['local wp-env credentials', 'WordPress local salts'],
                'blockers' => ['Requires Docker/wp-env availability and may fail on upstream trunk regressions.'],
            ]),
        ];
    }

    public function evaluate(): ReadinessFinding
    {
        $profiles = self::defaultProfiles();
        $blocked = array_values(array_filter(
            $profiles,
            static fn (DeploymentProfile $profile): bool => $profile->blockers !== [],
        ));

        if ($blocked === []) {
            return new ReadinessFinding(
                'deployment',
                ReadinessFinding::STATUS_PASS,
                'Deployment profiles are fully specified and have no known blockers.',
                array_map(static fn (DeploymentProfile $profile): string => 'profile:' . $profile->name, $profiles),
                'docs',
                false,
                'None',
            );
        }

        return new ReadinessFinding(
            'deployment',
            ReadinessFinding::STATUS_ENVIRONMENT_GATED,
            'Deployment profiles are specified; some require environment verification.',
            array_map(static fn (DeploymentProfile $profile): string => 'profile:' . $profile->name, $blocked),
            'docs',
            false,
            'Verify environment-gated deployment profiles on their target infrastructure.',
        );
    }

    /**
     * @param array{
     *     name: string,
     *     packageShape: string,
     *     buildCommands: list<string>,
     *     dependencies: list<string>,
     *     secrets: list<string>,
     *     blockers?: list<string>
     * } $attributes
     */
    private static function profile(array $attributes): DeploymentProfile
    {
        return DeploymentProfile::fromArray($attributes);
    }
}
