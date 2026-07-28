<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Admin\Errors;

defined('ABSPATH') || exit;

use Throwable;
use WP_Error;

/**
 * Makes every human-facing admin refusal a CoreX page (spec 083, FR-001).
 *
 * ## Why a `wp_die_handler` filter is the right instrument here, and was not before
 *
 * Spec 079 declined to install one, and DECISIONS #174 records that. Its concern was real but
 * different: the access-request form was posting a browser at a REST route, and the tempting fix was
 * content negotiation — one endpoint answering HTML or JSON depending on who asked. That would have
 * silently changed what every API consumer received. Nothing here reopens it; no route changes
 * behaviour, and nothing inspects `Accept`.
 *
 * What the narrower reading cost was measured before this class was written: nine of eleven admin
 * addresses still rendered WordPress's white box to a real subscriber, including CoreX's own Careers
 * screen, because `AccessDeniedGate` can only match `admin.php?page=corex-*`
 * (`specs/083-admin-error-surface/evidence/before/refusal-matrix.md`).
 *
 * ## The machine boundary is core's, not ours
 *
 * `wp_die()` chooses its handler by request type *before* any filter runs
 * (`wp-includes/functions.php:3791-3849`): Ajax, JSON/REST, JSONP, XML-RPC and XML/feeds each reach
 * their own filter, and only the final `else` reaches `wp_die_handler`. Filtering that one alone is
 * therefore not a promise to be careful — it is a branch a machine caller cannot arrive on. The
 * guards in {@see shouldHandle()} are a second fence, not the first.
 */
final class AdminDieHandler
{
    /**
     * What the last upstream hook said about this request, if anything.
     *
     * Request-scoped and set at most once: WordPress ends the request immediately after `wp_die()`,
     * so there is no second refusal for a stale marker to mislabel.
     */
    private ?string $marker = null;

    /** Guards against a refusal raised while rendering a refusal. */
    private bool $rendering = false;

    public function __construct(
        private readonly AdminErrorClassifier $classifier,
        private readonly AdminErrorPresenter $presenter,
    ) {
    }

    public function register(): void
    {
        // Priority 1 so the marker is set whatever else subscribes. AccessDeniedGate also listens
        // here and exits for CoreX pages; for everything else it returns and the die follows.
        add_action('admin_page_access_denied', [$this, 'noteDenial'], 1);
        add_action('check_admin_referer', [$this, 'noteNonceResult'], 1, 2);
        add_filter('wp_die_handler', [$this, 'handlerFor']);
    }

    public function noteDenial(): void
    {
        $this->marker = AdminErrorClassifier::MARKER_DENIED;
    }

    /**
     * Record a failed nonce check, so the refusal that follows is described as an expired link
     * rather than as a missing capability.
     *
     * **`log-out` is deliberately excluded.** `wp_nonce_ays('log-out')` reaches `wp_die()` at 403
     * like any refusal, but it is a confirmation prompt — "Do you really want to log out?" — and
     * the link it offers is the one that completes the action. Presenting it as an error would
     * describe a normal, deliberate step as a failure and drop the only control on the page.
     *
     * @param string|int $action The nonce action WordPress was verifying.
     * @param mixed      $result False when the nonce did not verify.
     */
    public function noteNonceResult(string|int $action, mixed $result): void
    {
        if ($result !== false || $action === 'log-out') {
            return;
        }

        $this->marker = AdminErrorClassifier::MARKER_EXPIRED;
    }

    /**
     * @param  callable $default WordPress's own handler.
     * @return callable the handler `wp_die()` will call
     */
    public function handlerFor(callable $default): callable
    {
        if (! $this->shouldHandle()) {
            return $default;
        }

        return function (mixed $message, mixed $title = '', mixed $args = []): void {
            $this->render($message, $title, is_array($args) ? $args : []);
        };
    }

    /**
     * Whether this request is one a person is reading in wp-admin.
     *
     * `is_admin()` is true for `admin-ajax.php` as well, so Ajax is checked explicitly even though
     * core has already routed it elsewhere — the cost is one function call and the failure it
     * prevents is an HTML document returned to a script.
     */
    private function shouldHandle(): bool
    {
        if (defined('WP_CLI') && WP_CLI) {
            return false;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        return ! wp_doing_cron() && ! wp_doing_ajax() && is_admin();
    }

    /**
     * Send the CoreX document, or hand back to WordPress if anything about doing so fails.
     *
     * @param array<string,mixed> $args
     */
    private function render(mixed $message, mixed $title, array $args): void
    {
        if ($this->rendering) {
            _default_wp_die_handler($message, $title, $args);

            return;
        }

        $this->rendering = true;

        try {
            $error = $this->errorFor($message, $args);
            $this->sendHeaders($error->status);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the presenter returns a fully-escaped self-contained document.
            echo $this->presenter->document($error);
        } catch (Throwable) {
            // A broken error page is worse than WordPress's plain one: it is the surface somebody
            // reaches when something has already gone wrong, and a blank response there tells them
            // nothing at all.
            $this->rendering = false;
            _default_wp_die_handler($message, $title, $args);

            return;
        }

        $this->rendering = false;

        if (($args['exit'] ?? true) !== false) {
            exit;
        }
    }

    /**
     * @param array<string,mixed> $args
     */
    private function errorFor(mixed $message, array $args): AdminError
    {
        $status = isset($args['response']) ? (int) $args['response'] : 0;
        $status = $status > 0 ? $status : null;

        $error = $this->presenter
            ->make($this->classifier->classify($status, $this->marker), $status)
            ->withDetail($this->detail($message));

        return $this->withBackLink($error, $args);
    }

    private function sendHeaders(int $status): void
    {
        if (headers_sent()) {
            return;
        }

        status_header($status);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
    }

    /**
     * What the original caller said, kept verbatim for the presenter to render.
     *
     * `wp_die()` accepts a `WP_Error`, in which case the messages are the message.
     */
    private function detail(mixed $message): string
    {
        if ($message instanceof WP_Error) {
            return implode(' ', array_map('strval', $message->get_error_messages()));
        }

        return is_scalar($message) ? (string) $message : '';
    }

    /**
     * Honour `$args['back_link']` with a real destination.
     *
     * Core renders `javascript:history.back()`. A real referer is better — it survives a middle-click,
     * it is visible on hover, and it works with JavaScript off — so the link is only offered when
     * there genuinely is one to offer.
     *
     * @param array<string,mixed> $args
     */
    private function withBackLink(AdminError $error, array $args): AdminError
    {
        if (($args['back_link'] ?? false) !== true) {
            return $error;
        }

        // `wp_get_referer()` already passes the value through `wp_validate_redirect()`, so an
        // off-site Referer header comes back as `false` rather than becoming a link on a page
        // somebody reaches when they are already confused.
        $referer = wp_get_referer();
        if (! is_string($referer) || $referer === '') {
            return $error;
        }

        return $error->withActions([...$error->actions, [
            'label' => __('Go back', 'corex'),
            'url' => $referer,
            'primary' => false,
        ]]);
    }
}
