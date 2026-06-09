<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Template;

defined('ABSPATH') || exit;

/**
 * The merged subject and the layout-wrapped HTML body produced by the renderer.
 */
final class RenderedEmail
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
    ) {
    }
}
