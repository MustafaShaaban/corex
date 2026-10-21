<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail\Capture;

defined('ABSPATH') || exit;

/**
 * While a flow's marked test runs, every email it sends is held: kept in Email Studio and never
 * sent, on any site (spec 113, US3; the owner's decision SAFE-D3). What was held is listed for
 * the test's result.
 */
final class MailHold
{
    private int $depth = 0;

    /** @var list<array{to:list<string>,subject:string,capture_id:string,path:string}> */
    private array $held = [];

    /**
     * Run `$work` with every email held, and return its result with what was held.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return array{0:T,1:list<array{to:list<string>,subject:string,capture_id:string,path:string}>}
     */
    public function during(callable $work): array
    {
        if ($this->depth === 0) {
            $this->held = [];
        }
        ++$this->depth;
        try {
            $result = $work();
        } finally {
            --$this->depth;
        }

        $held = $this->held;
        if ($this->depth === 0) {
            $this->held = [];
        }

        return [$result, $held];
    }

    public function active(): bool
    {
        return $this->depth > 0;
    }

    /**
     * Note an email held while a hold is active; outside one, nothing is noted.
     *
     * @param list<string> $to
     */
    public function note(array $to, string $subject, string $captureId, string $path): void
    {
        if ($this->active()) {
            $this->held[] = ['to' => $to, 'subject' => $subject, 'capture_id' => $captureId, 'path' => $path];
        }
    }
}
