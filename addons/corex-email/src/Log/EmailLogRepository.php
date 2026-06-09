<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Log;

defined('ABSPATH') || exit;

use Corex\Database\Collection;
use Corex\Email\Message\EmailMessage;
use Corex\Repositories\PostRepository;

/**
 * Persists email audit records via the data layer (Principle III). Each attempt is
 * one private `corex_email_log` post with the status, recipients, and subject as
 * `corex_mail_*` meta; queryable by status.
 */
final class EmailLogRepository extends PostRepository implements EmailLogStore
{
    protected function model(): string
    {
        return EmailLog::class;
    }

    public function record(string $status, EmailMessage $message): ?int
    {
        $subject = $message->subject !== '' ? $message->subject : '(no subject)';

        $log = $this->create([
            'title'        => sprintf('%s — %s', $subject, current_time('mysql')),
            'status'       => 'private',
            'mail_status'  => $status,
            'recipients'   => implode(', ', $message->to),
            'mail_subject' => $message->subject,
        ]);

        return $log->id();
    }

    public function byStatus(string $status): Collection
    {
        return $this->query()->where('mail_status', $status)->get();
    }
}
