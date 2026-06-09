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
    'from' => [
        'name'    => '',
        'address' => '',
    ],
    'reply_to' => '',
];
