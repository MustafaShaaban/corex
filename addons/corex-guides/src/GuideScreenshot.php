<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides;

defined('ABSPATH') || exit;

/**
 * A captured picture of a real admin screen (spec 084, FR-015).
 *
 * `$captureId` is the load-bearing field, and it is why this is an object rather than a path string.
 * It is the name the capture script knows the shot by, so a screenshot and the instruction to
 * produce it are linked in both directions: the script can fail loudly for a shot it was asked for
 * and could not take, and a reviewer can find the code that produced any image in the tree. A bare
 * path gives neither, which is how documentation ends up with pictures nobody can regenerate.
 *
 * `$alt` is required, not optional. A screenshot with no text alternative is a step a screen-reader
 * user simply does not get, and the surrounding steps already have to describe the control by name,
 * so the material to write it always exists.
 */
final class GuideScreenshot
{
    public function __construct(
        public readonly string $captureId,
        public readonly string $alt,
    ) {
    }

    /**
     * The file this shot is expected at, relative to the add-on's screenshot directory.
     */
    public function fileName(): string
    {
        return $this->captureId . '.png';
    }
}
