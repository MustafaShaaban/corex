<?php

/**
 * Unit tests for recipient resolution (spec US3: FR-007, FR-008, SC-004).
 *
 * Fixed, role (via an injected directory), and dynamic (context path) specs resolve to a
 * validated address set; invalid or empty results are dropped.
 *
 * @package Corex\Tests\Unit\Mail
 */

declare(strict_types=1);

use Corex\Email\Recipients\RecipientResolver;
use Corex\Email\Recipients\UserDirectory;
use Corex\Email\Template\MailContext;

function directory(array $byRole): UserDirectory
{
    return new class($byRole) implements UserDirectory {
        public function __construct(private array $byRole)
        {
        }

        public function emailsInRole(string $role): array
        {
            return $this->byRole[$role] ?? [];
        }
    };
}

it('resolves fixed, role, and dynamic recipients in order', function () {
    $resolver = new RecipientResolver(directory(['administrator' => ['admin@example.com']]));
    $context  = new MailContext(['event' => ['submitter' => ['email' => 'user@example.com']]]);

    $result = $resolver->resolve([
        ['type' => 'fixed', 'value' => 'fixed@example.com'],
        ['type' => 'role', 'value' => 'administrator'],
        ['type' => 'dynamic', 'value' => 'event.submitter.email'],
    ], $context);

    expect($result['valid'])->toBe(['fixed@example.com', 'admin@example.com', 'user@example.com']);
});

it('drops invalid resolved addresses', function () {
    $resolver = new RecipientResolver(directory([]));

    $result = $resolver->resolve([
        ['type' => 'fixed', 'value' => 'good@example.com'],
        ['type' => 'fixed', 'value' => 'not-an-email'],
    ], new MailContext([]));

    expect($result['valid'])->toBe(['good@example.com'])
        ->and($result['dropped'])->toBe(['not-an-email']);
});

it('skips a dynamic path that resolves to nothing', function () {
    $resolver = new RecipientResolver(directory([]));

    $result = $resolver->resolve([['type' => 'dynamic', 'value' => 'missing.path']], new MailContext([]));

    expect($result['valid'])->toBe([]);
});

it('deduplicates the same address resolved twice', function () {
    $resolver = new RecipientResolver(directory(['administrator' => ['admin@example.com']]));

    $result = $resolver->resolve([
        ['type' => 'fixed', 'value' => 'admin@example.com'],
        ['type' => 'role', 'value' => 'administrator'],
    ], new MailContext([]));

    expect($result['valid'])->toBe(['admin@example.com']);
});
