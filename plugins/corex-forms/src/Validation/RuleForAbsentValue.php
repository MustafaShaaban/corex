<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation;

defined('ABSPATH') || exit;

/**
 * A rule that is also asked when its field was left out of the request.
 *
 * The validator skips an optional field that is absent, so none of its rules run. That is
 * right for a rule about the answer itself, and wrong for a rule about several fields ("at
 * least one of these three must be filled"), which sits on optional fields and has the most
 * to say when all of them are missing. A browser sends every field, empty, so it never
 * reaches the gap; a request written by hand does.
 *
 * A rule opts in by implementing this instead of `Rule`. It is then handed `null` for the
 * absent value, with the whole payload beside it. Nothing else changes for it, and no rule
 * that does not opt in is ever handed an absent value: a site's rule written before this
 * existed keeps the behaviour it was written against.
 */
interface RuleForAbsentValue extends Rule
{
}
