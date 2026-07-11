<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Routing;

defined('ABSPATH') || exit;

use Corex\Email\Message\EmailMessage;
use Corex\Email\Studio\EmailTemplateReference;

/**
 * Immutable result of preparing one routed template for delivery.
 */
final readonly class PreparedEmail
{
    public function __construct(
        public EmailMessage $message,
        public EmailTemplateReference $template,
    ) {
    }
}
