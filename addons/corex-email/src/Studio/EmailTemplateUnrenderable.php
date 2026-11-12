<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use DomainException;

/**
 * A template that cannot be drawn: an unknown variable, a partial that is not there, a layout
 * that was removed. Each field says why in a sentence that names it (spec 120, story 1).
 */
final class EmailTemplateUnrenderable extends DomainException
{
    /** @param array<string,string> $fields */
    public function __construct(public readonly array $fields)
    {
        parent::__construct(implode(' ', $fields));
    }
}
