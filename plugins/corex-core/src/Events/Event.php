<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Events;

defined('ABSPATH') || exit;

/**
 * Marker for an immutable event object. Implementations carry the data a listener
 * needs and nothing else; they are dispatched by class to their registered listeners.
 */
interface Event
{
}
