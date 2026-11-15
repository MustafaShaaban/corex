<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * A capped flow's responses could not be counted in time: another submission held the count for
 * longer than a visitor should wait (spec 122, FR-030). Nothing was stored; the visitor may send
 * again, and is told so.
 */
final class SubmissionBusyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('Many people are sending this form right now. Please try again in a moment.', 'corex'));
    }
}
