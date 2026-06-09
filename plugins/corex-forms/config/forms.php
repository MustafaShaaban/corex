<?php

/**
 * Forms defaults. `email.recipient` is where the contact form's notification is
 * sent (empty = the site admin email). Overridable via the Config engine
 * (`forms.email.recipient`).
 *
 * @package Corex\Forms
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    'email' => [
        'recipient' => '',
    ],
];
