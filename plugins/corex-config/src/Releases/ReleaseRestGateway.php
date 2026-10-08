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
        // WordPress has already used this nonce to decide who is asking. It is checked again
        // here because every one of these routes changes or reveals something a forged request
        // must not, and the rule belongs where the routes are.
        if (wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest') === false) {
            return $this->refused(403, self::NOT_SIGNED, __('This request was not signed by this screen. Reload the page and try again.', 'corex'));
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
