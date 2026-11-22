<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

/**
 * Puts what an editor set on a block (its anchor, its alignment, its extra classes) on the root element
 * of the markup its renderer printed (spec 136, FR-001 to FR-003; DECISIONS #670).
 *
 * The registrar passes every renderer's markup through it, so a renderer prints exactly one root element
 * and never handles the wrapper attributes itself.
 */
interface BlockWrapper
{
    /**
     * @param string       $markup      What the block's renderer printed. Empty markup comes back empty.
     * @param list<string> $rootClasses The classes that mark the root element, when the block names them in
     *                                  its block.json (`corex.root`): the root is the first element carrying
     *                                  any of them. Empty, or none matching: the root is the first element.
     */
    public function apply(string $markup, array $rootClasses = []): string;
}
