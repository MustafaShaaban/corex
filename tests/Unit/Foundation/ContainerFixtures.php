<?php

/**
 * Plain fixtures for ContainerTest. Required directly (not PSR-4 autoloaded,
 * since several classes share one file) and ignored by Pest as a non-test file.
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

namespace Corex\Tests\Fixtures\Container;

interface MailerContract
{
}

class Mailer
{
}

class Greeter
{
    public function __construct(public readonly Mailer $mailer)
    {
    }
}

class NeedsScalar
{
    public function __construct(public readonly string $name)
    {
    }
}

class HasOptionalScalar
{
    public function __construct(public readonly int $count = 5)
    {
    }
}

class CycleA
{
    public function __construct(public readonly CycleB $b)
    {
    }
}

class CycleB
{
    public function __construct(public readonly CycleA $a)
    {
    }
}
