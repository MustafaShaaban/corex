<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/**
 * Dispatched when a site's own email design could not be used and the standard one was sent in
 * its place (spec 106, FR-017). The email went; what failed is the site's design, and somebody
 * who can mend it has to be told.
 */
final class EmailDesignFailedEvent implements Event
{
    /** The design threw while it was drawing. */
    public const THREW = 'threw';

    /** The design answered with nothing. */
    public const EMPTY = 'empty';

    /** The design answered with a document that does not hold the message. */
    public const WITHOUT_MESSAGE = 'without_message';

    /** What the site gave is not an email design. */
    public const NOT_A_DESIGN = 'not_a_design';

    /** The site's filter of the brand threw; the email was drawn from the brand it had before. */
    public const BRAND_THREW = 'brand_threw';

    /**
     * @param string $design The class of what the site gave, or the filter that failed, to find it by.
     * @param string $reason One of this class's constants.
     * @param string $detail One short line of what a thrown error said, or ''. Never the message of the email.
     */
    public function __construct(
        public readonly string $design,
        public readonly string $reason,
        public readonly string $detail = '',
    ) {
    }
}
