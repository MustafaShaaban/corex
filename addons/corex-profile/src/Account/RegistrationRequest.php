<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Account;

defined('ABSPATH') || exit;

/**
 * An immutable, sanitized registration input. Controllers build it from the request;
 * {@see AccountService::register()} validates it. Keeps the service signature stable
 * as fields evolve.
 */
final class RegistrationRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $passwordConfirm,
        public readonly string $username = '',
        public readonly bool $consent = false,
    ) {
    }
}
