<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

/**
 * A site's design that says which kinds of email it draws (spec 120, story 3).
 *
 * A design given through `corex_email_design` that implements only {@see EmailDesign} draws
 * replies, as every such design has since spec 106: any other email is drawn in CoreX's standard
 * design. One that implements this is given every kind it accepts.
 *
 *     final class AcmeEmailDesign implements KindAwareEmailDesign
 *     {
 *         public function accepts(string $kind): bool
 *         {
 *             return in_array($kind, [EmailKind::REPLY, EmailKind::NEWSLETTER], true);
 *         }
 *         // render() as for any EmailDesign; $content->kind says which this one is.
 *     }
 */
interface KindAwareEmailDesign extends EmailDesign
{
    /** @param string $kind One of {@see EmailKind}'s constants. */
    public function accepts(string $kind): bool;
}
