<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DomainException;

/**
 * Signals an optimistic draft-version or checksum conflict.
 */
final class FlowConflictException extends DomainException
{
}
