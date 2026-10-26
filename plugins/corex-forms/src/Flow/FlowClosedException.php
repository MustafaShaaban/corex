<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DomainException;

/**
 * A submission reached a flow that is not taking answers. It carries what the visitor is told
 * (spec 122, FR-029), so the route answers with the notice and not a technical error.
 */
final class FlowClosedException extends DomainException
{
    public function __construct(public readonly FlowNotice $notice)
    {
        parent::__construct($notice->message);
    }
}
