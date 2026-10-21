<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail\Capture;

defined('ABSPATH') || exit;

/**
 * The name of what is sending the email in progress, for the Email Studio log: "Form
 * notification", "Newsletter: new post", "WordPress: password reset" (spec 113, research R4).
 *
 * CoreX code that sends wraps the send in {@see during()}; mail sent inside a name is CoreX's own,
 * which the "Capture only CoreX mail" switch reads. WordPress builds its own emails through
 * filters that run just before `wp_mail()`, so a name can also be left for the next email
 * ({@see next()}), which reads it once.
 */
final class MailPath
{
    /** @var list<string> */
    private array $names = [];

    private ?string $next = null;

    /**
     * @template T
     *
     * @param callable(): T $send
     *
     * @return T
     */
    public function during(string $name, callable $send): mixed
    {
        $this->names[] = $name;
        try {
            return $send();
        } finally {
            array_pop($this->names);
        }
    }

    /** The innermost name in force, or null when no CoreX code is sending. */
    public function current(): ?string
    {
        return $this->names === [] ? null : $this->names[array_key_last($this->names)];
    }

    /** Name the next email, which is not CoreX's own (WordPress core mail). */
    public function next(string $name): void
    {
        $this->next = $name;
    }

    /** The name left for this email, once: a second email is not named by it. */
    public function takeNext(): ?string
    {
        $name = $this->next;
        $this->next = null;

        return $name;
    }
}
