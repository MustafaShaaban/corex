<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Mail\SubmissionAddress;

/**
 * A submission's place in the Submissions inbox: the screen, opened on that submission's pane.
 *
 * The one place the address is written. An email about a submission asks for it through
 * {@see SubmissionAddress}; a notification inside the admin, which is this plugin's own, calls
 * {@see self::to()}.
 */
final class InboxSubmissionAddress implements SubmissionAddress
{
    /** The screen's page, as {@see SubmissionsInboxScreen} registers it. */
    private const PAGE = 'corex-submissions';

    /** What the inbox reads from its address to open one submission on arrival (`inbox.js`). */
    private const OPENS = 'corex_submission';

    public function of(int $submissionId): string
    {
        return self::to($submissionId);
    }

    public static function to(int $submissionId): string
    {
        return add_query_arg(
            ['page' => self::PAGE, self::OPENS => (string) $submissionId],
            admin_url('admin.php'),
        );
    }
}
