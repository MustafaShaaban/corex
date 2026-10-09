<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

/**
 * Puts back an option a test read before it changed it.
 *
 * The integration suite runs against a real developer install, so an option a test deletes
 * belongs to somebody. This was a function in one test file that a second test file called: the
 * second passed in the whole suite, where Pest has read both, and run alone its `afterEach` threw
 * "Call to undefined function" after the test had deleted the site's operations mode and its
 * history. Nothing put them back.
 */
final class SavedOption
{
    /**
     * Put an option back exactly as it was, including "it did not exist".
     *
     * @param mixed $saved The value read before the test, or null when the option was absent.
     */
    public static function restore(string $key, mixed $saved): void
    {
        if ($saved === null) {
            delete_option($key);

            return;
        }

        update_option($key, $saved, false);
    }
}
