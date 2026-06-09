<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Log;

defined('ABSPATH') || exit;

use Corex\Models\Model;

/**
 * One email audit record. Status, recipients, and subject are declared fields, so
 * they persist as `corex_mail_*` meta and the log is queryable by status.
 */
final class EmailLog extends Model
{
    public static function postType(): string
    {
        return 'corex_email_log';
    }

    /**
     * @return array<string,string>
     */
    public static function fields(): array
    {
        return [
            'mail_status'  => 'corex_mail_status',
            'recipients'   => 'corex_mail_to',
            'mail_subject' => 'corex_mail_subject',
        ];
    }
}
