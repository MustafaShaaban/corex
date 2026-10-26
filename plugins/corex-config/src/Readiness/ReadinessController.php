<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Readiness;

defined('ABSPATH') || exit;

use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeController;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Config\Operations\ProductionLaunchService;
use Corex\Config\Operations\ProductionReadinessSnapshotFactory;
use Corex\Http\ResponseEnvelope;
use DateTimeImmutable;
use WP_REST_Response;

/**
 * `GET corex/v1/readiness`: the one launch checklist, in the gate's words, for the Setup Wizard's
 * launch step (spec 118, US-WIZ-06). It carries what the step needs to offer "Go live" through the
 * Operations screen's own mode form: where that form posts, its nonce and the phrase it asks for.
 * The form, not this route, changes the mode, so the confirmation and the history row are the
 * Operations screen's. Read with the capability the wizard and Operations need.
 */
final class ReadinessController
{
    public function __construct(
        private readonly ProductionReadinessSnapshotFactory $readiness,
        private readonly LaunchChecklist $checklist,
        private readonly OperationsMode $modes,
        private readonly OperationsModeStore $store,
    ) {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/readiness', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => [$this, 'canRead'],
        ]);
    }

    public function canRead(): bool
    {
        return current_user_can('manage_options');
    }

    public function show(): WP_REST_Response
    {
        $mode = $this->store->current();

        return new WP_REST_Response(ResponseEnvelope::success([
            'checklist' => $this->checklist->build(
                $this->readiness->fromCurrentSite(new DateTimeImmutable('now')),
                ReadinessWords::GATE,
            ),
            'mode'      => [
                'key'        => $mode,
                'label'      => $this->modes->describe($mode)['label'],
                'production' => $mode === OperationsMode::PRODUCTION,
            ],
            'goLive'    => [
                'url'        => admin_url('admin-post.php'),
                'action'     => OperationsModeController::ACTION,
                'nonceField' => OperationsModeController::NONCE,
                'nonce'      => wp_create_nonce(OperationsModeController::ACTION),
                'mode'       => OperationsMode::PRODUCTION,
                'phrase'     => ProductionLaunchService::REQUIRED_PHRASE,
                'return'     => OperationsModeController::RETURN_SETUP,
            ],
        ])->toArray());
    }
}
