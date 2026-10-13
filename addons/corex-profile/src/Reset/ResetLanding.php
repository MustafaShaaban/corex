<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Reset;

defined('ABSPATH') || exit;

use WP_Error;

/**
 * Receives a visitor who follows the link of a reset email to the account page.
 *
 * Two requests. The first carries the login and the key in its address: they are put away in
 * the {@see ResetTicket} and the visitor is sent to the same page without them. The second is
 * the page itself: the key is checked with WordPress, and the account block is told what to
 * show. Neither is a page a cache may keep.
 */
final class ResetLanding
{
    private ResetState $state;

    public function __construct(
        private readonly ResetTicket $ticket,
        private readonly ResetLink $links,
    ) {
        $this->state = ResetState::none();
    }

    public function register(): void
    {
        // Before the template, which is where a cookie can still be set.
        add_action('template_redirect', [$this, 'onRequest'], 1);
    }

    /**
     * Takes away a ticket that has nothing more to say: a key WordPress refuses, and the note
     * that the password was changed, which is said once.
     */
    public function onRequest(): void
    {
        if ($this->arrivesWithTheKey()) {
            $this->ticket->keep($this->asked(ResetLink::ARG_LOGIN), $this->asked(ResetLink::ARG_KEY));
            nocache_headers();
            wp_safe_redirect($this->links->withoutTheKey(get_queried_object_id()));
            exit;
        }

        if ($this->asked(ResetLink::ARG_STEP) === '') {
            return;
        }

        nocache_headers();
        $this->state = $this->read();

        if (in_array($this->state->step, [ResetState::EXPIRED, ResetState::DONE], true)) {
            $this->ticket->forget();
        }
    }

    /**
     * What the account block shows on this request.
     */
    public function state(): ResetState
    {
        return $this->state;
    }

    /**
     * Only on a page or a post: the email links to one, and the visitor is sent on to its own
     * address, not to one made from what was asked for.
     */
    private function arrivesWithTheKey(): bool
    {
        return $this->asked(ResetLink::ARG_LOGIN) !== ''
            && $this->asked(ResetLink::ARG_KEY) !== ''
            && is_singular();
    }

    private function read(): ResetState
    {
        if ($this->ticket->wasUsed()) {
            return ResetState::done();
        }

        $held = $this->ticket->held();
        if ($held === null) {
            return ResetState::none();
        }

        return check_password_reset_key($held['key'], $held['login']) instanceof WP_Error
            ? ResetState::expired()
            : ResetState::choose($held['login'], $held['key']);
    }

    private function asked(string $name): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only: an address changes nothing, and WordPress checks the key.
        $sent = $_GET[$name] ?? '';

        return is_string($sent) ? wp_unslash($sent) : '';
    }
}
