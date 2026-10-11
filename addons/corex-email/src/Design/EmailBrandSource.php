<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

/**
 * Says, when an email is about to be drawn, whose it is and in which colours.
 */
interface EmailBrandSource
{
    public function brand(): EmailBrand;
}
