<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

/**
 * The design around a message: header, body, footer (spec 106).
 *
 * CoreX has one, {@see StandardEmailDesign}. A site that wants its own implements this.
 */
interface EmailDesign
{
    /**
     * @return string A whole HTML document, holding the content's message.
     */
    public function render(EmailContent $content, EmailBrand $brand): string;
}
