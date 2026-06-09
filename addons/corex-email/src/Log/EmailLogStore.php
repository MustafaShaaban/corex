<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Log;

defined('ABSPATH') || exit;

use Corex\Email\Message\EmailMessage;

/**
 * Records one delivery attempt's outcome. The interface keeps the service free of
 * the persistence boundary so it is headless-testable (the WordPress-backed
 * implementation is the repository). Returns the audit record id, or null.
 */
interface EmailLogStore
{
    public function record(string $status, EmailMessage $message): ?int;
}
