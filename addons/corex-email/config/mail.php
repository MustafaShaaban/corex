<?php

/**
 * Corex Mail defaults. The from-identity and reply-to for outgoing mail; empty
 * values fall back to the site identity (admin email / blog name). Overridable via
 * the Config engine (`mail.from.name`, `mail.from.address`, `mail.reply_to`).
 *
 * @package Corex\Email
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    // Live delivery remains fail-closed until both values are deliberately set.
    'provider'      => '',
    'live_delivery' => false,
    'from' => [
        'name'    => '',
        'address' => '',
    ],
    'reply_to' => '',
    'variables' => [
        'user.name'        => ['type' => 'text', 'label' => 'User name'],
        'user.email'       => ['type' => 'email', 'label' => 'User email'],
        'recipient.name'   => ['type' => 'text', 'label' => 'Recipient name'],
        'recipient.email'  => ['type' => 'email', 'label' => 'Recipient email'],
        'site.name'        => ['type' => 'text', 'label' => 'Site name'],
        'site.url'         => ['type' => 'url', 'label' => 'Site URL'],
        'action.url'       => ['type' => 'url', 'label' => 'Action URL'],
        'links.unsubscribe'=> ['type' => 'url', 'label' => 'Unsubscribe URL'],
        'links.preferences'=> ['type' => 'url', 'label' => 'Preferences URL'],
        'links.privacy'    => ['type' => 'url', 'label' => 'Privacy URL'],
        'submission.name'  => ['type' => 'text', 'label' => 'Submission name'],
        'submission.email' => ['type' => 'email', 'label' => 'Submission email'],
        'submission.body'  => ['type' => 'text', 'label' => 'Submission body'],
    ],
];
