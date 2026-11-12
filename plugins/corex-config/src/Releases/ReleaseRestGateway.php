<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use InvalidArgumentException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Who may use the Releases routes, and the shape every one of them answers in (spec 107, plan
 * D12 and D13).
 *
 * Every answer carries the installer's mark. A shared host puts pages of its own in front of a
 * site (a challenge, a "too many requests", a maintenance page), and they arrive with status
 * 200 and a body that is not this. The screen takes an answer as one only if it is marked, and
 * anything else as "wait and ask again": so the mark is on refusals too, which are answers.
 */
final class ReleaseRestGateway
{
    /** The key every answer of these routes carries. */
    public const MARK = 'corex_release';

    public const NOT_SIGNED = 'not_signed';

    /** An Application Password on a site that does not let one install a release (DECISIONS #512). */
    public const PUSH_OFF = 'push_off';

    /** An Application Password over plain HTTP on a site that is not a local one. */
    public const PUSH_NEEDS_HTTPS = 'push_needs_https';

    /** Signed in, but by an account that may not install releases here. */
    public const NOT_ALLOWED = 'not_allowed';

    public function __construct(private readonly ReleaseCaller $caller)
    {
    }

    /**
     * Installing a release is its own ability. On a network it is the network's to do, from the
     * main site: a release replaces code every site runs.
     */
    public function mayManage(): bool
    {
        if (! current_user_can(CorexAbility::MANAGE_RELEASES)) {
            return false;
        }

        return ! is_multisite() || (is_super_admin() && is_main_site());
    }

    /**
     * Run one of the routes and answer for it.
     *
     * @param callable():array<string,mixed> $answer What the route does; what it returns is the answer's data.
     */
    public function answer(WP_REST_Request $request, callable $answer): WP_REST_Response
    {
        $unsigned = $this->unsigned($request);

        if ($unsigned !== null) {
            return $unsigned;
        }

        try {
            return $this->marked(200, ['ok' => true, 'data' => $answer()]);
        } catch (ReleaseUploadOutOfStep $outOfStep) {
            return $this->marked(409, ['ok' => false, 'reason' => 'out_of_step', 'received' => $outOfStep->received]);
        } catch (ReleaseRefused $refused) {
            return $this->refused(422, $refused->reason, $refused->getMessage());
        } catch (InvalidArgumentException) {
            // A name that is not only a name: the store will not place a file by it.
            return $this->refused(422, ReleaseUpload::BAD_NAME, __('That is not the name of a package on this site.', 'corex'));
        }
    }

    /**
     * Null when the request is signed as these routes need: by the screen's nonce, or by an
     * Application Password this site lets install a release (spec 123, FR-014). Otherwise the
     * refusal, saying which condition failed.
     */
    private function unsigned(WP_REST_Request $request): ?WP_REST_Response
    {
        // WordPress has already used this nonce to decide who is asking. It is checked again
        // here because every one of these routes changes or reveals something a forged request
        // must not, and the rule belongs where the routes are.
        if (wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest') !== false) {
            return null;
        }

        // A request WordPress signed in by cookie is never also signed in by an Application
        // Password, so a page that lost its nonce still lands here, refused as it always was.
        // An Application Password carries no cookie, so nothing on another page can forge it.
        if ($this->caller->applicationPassword() === null) {
            return $this->refused(403, self::NOT_SIGNED, __('This request was not signed by this screen. Reload the page and try again.', 'corex'));
        }

        if (! $this->caller->pushAllowed()) {
            return $this->refused(403, self::PUSH_OFF, sprintf(
                /* translators: %s: a line of PHP for the site's wp-config.php. */
                __('This site does not let a release be installed with an Application Password. To allow it, add %s to its wp-config.php.', 'corex'),
                "define( '" . ReleaseCaller::PUSH_ALLOWED_BY . "', true );",
            ));
        }

        if (! $this->caller->overHttps()) {
            return $this->refused(403, self::PUSH_NEEDS_HTTPS, __('A release is installed with an Application Password only over HTTPS.', 'corex'));
        }

        // The route's permission callback has asked this already; asked again for the reason
        // the nonce is.
        if (! $this->mayManage()) {
            return $this->refused(403, self::NOT_ALLOWED, __('This account may not install releases on this site.', 'corex'));
        }

        return null;
    }

    private function refused(int $status, string $reason, string $message): WP_REST_Response
    {
        return $this->marked($status, ['ok' => false, 'reason' => $reason, 'message' => $message]);
    }

    /**
     * @param array<string,mixed> $answer
     */
    private function marked(int $status, array $answer): WP_REST_Response
    {
        return new WP_REST_Response([self::MARK => 1, ...$answer], $status);
    }
}
