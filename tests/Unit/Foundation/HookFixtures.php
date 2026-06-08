<?php

/**
 * Subscriber fixtures for the HookRegistry tests. Required directly and ignored
 * by Pest as a non-test file.
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

namespace Corex\Tests\Fixtures\Hooks;

use Corex\Hooks\SubscribesToHooks;

final class ActionSubscriber implements SubscribesToHooks
{
    public function hooks(): array
    {
        return ['init' => 'onInit'];
    }

    public function onInit(): void
    {
    }
}

final class FilterSubscriber implements SubscribesToHooks
{
    public function hooks(): array
    {
        return ['the_title' => ['filterTitle', 20, 2]];
    }

    public function filterTitle(string $title): string
    {
        return $title;
    }
}
