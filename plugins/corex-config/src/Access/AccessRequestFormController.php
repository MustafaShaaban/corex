<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AccessRequestSurfaceState;
use Corex\Admin\AdminPage;
use Corex\Admin\StandalonePage;
use Corex\Security\Admin\AdminGuard;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Where the "Request access" button posts (spec 079, FR-001).
 *
 * Before this existed the denied surface's form posted the browser straight at
 * `rest_url('corex/v1/access/requests')`. Nothing was broken: the controller returned JSON because
 * it is a REST endpoint, and the browser rendered what it was given. The request even succeeded —
 * so the person asking for help was shown an operation envelope and had no way to tell that anyone
 * would ever see it.
 *
 * This handler is the browser's half of that endpoint. It calls the same {@see AccessService}
 * method the REST route calls, so the two cannot drift, then redirects (POST/redirect/GET) back to
 * the screen the person was refused from. The REST route is untouched and still answers JSON.
 *
 * The redirect carries no arguments at all. Success is read back out of the database by the denied
 * surface, which means a refresh, a bookmark and a visit tomorrow all show the same true state, and
 * no confirmation can be conjured by editing the URL.
 */
final class AccessRequestFormController
{
    public const ACTION = 'corex_access_request';
    public const NONCE  = 'corex_access_request_nonce';

    /**
     * The submission is made by someone who by definition holds no CoreX ability — being refused is
     * why they are here. `read` is the capability that distinguishes a signed-in user from an
     * anonymous one, and it is the only thing this handler needs to be true.
     */
    private const CAPABILITY = 'read';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AccessService $service,
        private readonly AccessRequestFlash $flash,
    ) {
    }

    /**
     * No `register()`. The provider hooks `admin_post_{ACTION}` to a closure that resolves this
     * class only when the action fires — see the note there. Handing out a `register()` would
     * invite `make(...)->register()` at boot, which is the thing being avoided.
     */
    public function handle(): void
    {
        $destination = $this->decide();

        if ($destination === null) {
            $this->refuse();
        }

        wp_safe_redirect($destination);
        exit;
    }

    /**
     * Everything the submission decides, with nothing that ends the request.
     *
     * Split from {@see handle()} because `exit` cannot be caught: a test driving the whole handler
     * takes the test runner down with it, so the decisions would go unproven and the only honest
     * coverage would be a browser. Here each decision is an ordinary return value, and `handle()`
     * is two WordPress calls that Playwright exercises for real.
     *
     * @return string|null Where to send the browser, or null when the submission is refused
     *                     outright — no session, or no valid nonce.
     */
    public function decide(): ?string
    {
        if (! $this->guard->verifiedPost(self::NONCE, self::ACTION, self::CAPABILITY)) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
        $section = isset($_POST['corex_section']) ? sanitize_key(wp_unslash($_POST['corex_section'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
        $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

        $slug = AdminPage::pageSlugForSection($section);
        if ($slug === null) {
            // No CoreX screen answers to this section, so there is nowhere to send them back to and
            // nothing coherent to request. Nothing is created.
            return admin_url();
        }

        // The screen they came from. It re-renders its designed 403 and, once a pending request
        // exists, shows the confirmation in place of the form.
        $screen  = admin_url('admin.php?page=' . $slug);
        $ability = AdminPage::requestAbilityFor($section);
        $userId  = get_current_user_id();

        if (trim($reason) === '') {
            $this->flash->store($userId, AccessRequestSurfaceState::PROBLEM_INVALID, $reason);

            return $screen;
        }

        // Server-side, so a second tab, a double-click and a replayed POST are all covered. A
        // disabled button covers none of them.
        if ($this->service->pendingRequestFor($userId, $ability) !== null) {
            return $screen;
        }

        $now = new DateTimeImmutable('now');

        try {
            $this->service->requestAccess($userId, $ability, null, $reason, $now, $now->modify('+7 days'));
        } catch (InvalidArgumentException) {
            // The service refused the details themselves — an over-long reason is the reachable
            // case. The typed text comes back with the form so it is not lost.
            $this->flash->store($userId, AccessRequestSurfaceState::PROBLEM_INVALID, $reason);
        } catch (Throwable $failure) {
            // Anything else is ours, not theirs. They get a reference; the detail goes to the log.
            $reference = strtoupper(substr(md5(uniqid('', true)), 0, 8));
            $this->flash->store($userId, AccessRequestSurfaceState::PROBLEM_FAILED, $reason, $reference);
            $this->log($reference, $failure);
        }

        return $screen;
    }

    private function refuse(): never
    {
        status_header(403);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        echo StandalonePage::fromCore()->notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StandalonePage returns a fully-escaped self-contained document.
            __('That request could not be sent', 'corex'),
            __('Your session or the link you used expired before the request was sent. Open the screen again and resend it — nothing was recorded.', 'corex'),
            admin_url(),
            __('Back to Dashboard', 'corex'),
        );
        exit;
    }

    private function log(string $reference, Throwable $failure): void
    {
        if (! (defined('WP_DEBUG') && WP_DEBUG)) {
            return;
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only diagnostic.
        error_log(sprintf('CoreX access request %s failed: %s', $reference, $failure->getMessage()));
    }
}
