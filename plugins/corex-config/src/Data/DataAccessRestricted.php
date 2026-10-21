<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use DomainException;

/**
 * A person asked the Data screen for records that belong to a screen they may not open (spec 112,
 * FR-029). Its own type, as {@see PersonalDataNotAllowed} is: the gateway answers it 403 by what it
 * is, not by reading the words of a translated message.
 */
final class DataAccessRestricted extends DomainException
{
    public static function forModel(): self
    {
        return new self(__('These records belong to a screen you do not have access to.', 'corex'));
    }

    /** @param list<string> $labels The models asked for, every one of them restricted. */
    public static function forEveryModelOf(array $labels): self
    {
        return new self(sprintf(
            /* translators: %s: the names of data models, comma-separated. */
            __('Nothing was exported: every model asked for belongs to a screen you do not have access to (%s).', 'corex'),
            implode(', ', $labels),
        ));
    }
}
