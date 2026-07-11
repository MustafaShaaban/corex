<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Routing\EmailRouteRepository;

/**
 * Typed persistence ports used by the Email Studio REST boundary.
 *
 * This immutable record keeps repository wiring explicit without turning the
 * controller into a service locator.
 */
final readonly class EmailStudioRepositories
{
    public function __construct(
        public EmailTemplateRepository $templates,
        public EmailLayoutRepository $layouts,
        public EmailPartialRepository $partials,
        public EmailRouteRepository $routes,
        public CapturedEmailRepository $captures,
        public EmailAttemptRepository $attempts,
    ) {
    }
}
