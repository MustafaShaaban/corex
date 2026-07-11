<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Kit\Company\CompanyBlueprint;
use Corex\Kit\Provisioning\BlueprintKitProvisioner;
use Corex\Kit\Setup\SetupWizardController;
use Corex\Provisioning\KitProvisioner;

/**
 * Registers the starter-kit Blueprint registry and the Company Website blueprint.
 * The kit's FSE templates/parts live in the theme (the skin); this provider only
 * contributes the discoverable manifest. It also binds the corex-core KitProvisioner
 * seam (spec 042) so corex-config can drive activation without depending on this add-on.
 */
final class KitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(BlueprintRegistry::class);

        $this->container->singleton(
            KitProvisioner::class,
            static fn (ContainerInterface $c): BlueprintKitProvisioner => new BlueprintKitProvisioner(
                $c->make(BlueprintRegistry::class),
                $c->make(SetupWizard::class),
                $c->make(BlueprintActivator::class),
            ),
        );
    }

    public function boot(): void
    {
        $this->container->make(BlueprintRegistry::class)->register(
            $this->container->make(CompanyBlueprint::class),
        );

        // The setup wizard is admin-only; its planning core is the pure SetupWizard.
        if (is_admin()) {
            $this->container->make(SetupWizardScreen::class)->register();
        }

        // The nine-step wizard REST surface (cap + nonce gated) for the React/live wizard.
        add_action('rest_api_init', function (): void {
            $this->container->make(SetupWizardController::class)->register();
        });
    }
}
