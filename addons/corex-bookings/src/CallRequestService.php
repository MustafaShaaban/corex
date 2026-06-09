<?php

/**
 * @package Corex\Bookings
 */

declare(strict_types=1);

namespace Corex\Bookings;

defined('ABSPATH') || exit;

use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;

/**
 * Orchestrates a call request: validate the leader + the contact fields, store it,
 * and notify the leader + confirm the visitor. Every rejection short-circuits before
 * any side effect (FR-004). Captcha/honeypot are the endpoint's job.
 */
final class CallRequestService
{
    public function __construct(
        private readonly CallRequestStore $store,
        private readonly LeaderDirectory $leaders,
        private readonly Mailer $mailer,
    ) {
    }

    /**
     * @param array<string,mixed> $data name/email/phone/preferred_time/message
     */
    public function request(string $leaderId, array $data): CallRequestResult
    {
        $leader = $this->leaders->find($leaderId);
        if ($leader === null) {
            return CallRequestResult::rejected('unknown_leader');
        }

        $name  = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return CallRequestResult::rejected('invalid_fields');
        }

        $phone         = (string) ($data['phone'] ?? '');
        $preferredTime = (string) ($data['preferred_time'] ?? '');

        $id = $this->store->create([
            'leader_id'      => $leaderId,
            'name'           => $name,
            'email'          => $email,
            'phone'          => $phone,
            'preferred_time' => $preferredTime,
            'message'        => (string) ($data['message'] ?? ''),
            'status'         => 'requested',
        ]);

        $this->mailer->send(new MailRequest(
            to: [$leader['email']],
            templateName: 'call-request-leader',
            context: ['name' => $name, 'phone' => $phone, 'preferred_time' => $preferredTime],
        ));

        $this->mailer->send(new MailRequest(
            to: [$email],
            templateName: 'call-request-confirm',
            context: ['name' => $name, 'leader' => $leader['name']],
        ));

        return CallRequestResult::stored($id);
    }
}
