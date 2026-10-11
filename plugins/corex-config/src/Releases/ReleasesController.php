<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The Releases screen's routes (spec 107, slices 3 and 4).
 *
 *     GET  releases                         what is installed, what the host cannot do, the packages here,
 *                                           and an installation in progress
 *     GET  releases/uploads/{hash}          how much of an upload the site holds
 *     POST releases/uploads/{hash}          a part of a package, as the request's body
 *     POST releases/uploads/{hash}/complete all of it has been sent: keep it, if it is what was sent
 *     POST releases/inspect                 what a package on the site is, or why it is refused
 *     POST releases/install/prepare         what installing a package would do, and the way back
 *     POST releases/install/confirm         the version, typed
 *     POST releases/install/step            the next piece: unpack, verify, copy tables, swap
 *     POST releases/install/finish          the database step, answered by the release just installed
 *     POST releases/install/cancel          give up an installation that has moved nothing
 *
 * Only `step` and `finish` change a file of the site outside `wp-content/corex-releases/`.
 *
 * `install/finish` is a contract between releases: the release that asks for it is not the
 * release that answers. Its path, that it takes no argument, and the shape of its answer
 * (`done`, `version`, `changes`, or where the installation stands) stay as they are for as
 * long as a release that speaks `ReleaseProgress::PROTOCOL` 1 can be installed over.
 */
final class ReleasesController
{
    private const HASH = '(?P<hash>[0-9a-f]{64})';

    public function __construct(
        private readonly ReleaseRestGateway $gateway,
        private readonly ReleaseDesk $desk,
        private readonly ReleaseUpload $upload,
        private readonly ReleaseInstallation $installation,
        private readonly ReleaseCompletion $completion,
    ) {
    }

    public function register(): void
    {
        $this->route('/releases', 'GET', 'overview');
        $this->route('/releases/uploads/' . self::HASH, 'GET', 'received');
        $this->route('/releases/uploads/' . self::HASH, 'POST', 'append');
        $this->route('/releases/uploads/' . self::HASH . '/complete', 'POST', 'complete');
        $this->route('/releases/inspect', 'POST', 'inspect');
        $this->route('/releases/install/prepare', 'POST', 'prepare');
        $this->route('/releases/install/confirm', 'POST', 'confirm');
        $this->route('/releases/install/step', 'POST', 'step');
        $this->route('/releases/install/finish', 'POST', 'finish');
        $this->route('/releases/install/cancel', 'POST', 'cancel');
    }

    public function overview(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->answer($request, fn (): array => $this->desk->overview());
    }

    public function received(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->answer($request, fn (): array => [
            'received' => $this->upload->received((string) $request->get_param('hash')),
        ]);
    }

    public function append(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->answer($request, fn (): array => [
            'received' => $this->upload->append(
                (string) $request->get_param('hash'),
                absint($request->get_param('offset')),
                // The part itself: bytes of a zip, taken as they are and not as text. What they
                // amount to is checked against the hash when the last of them has arrived.
                (string) $request->get_body(),
            ),
        ]);
    }

    public function complete(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->answer($request, fn (): array => [
            'package' => $this->upload->complete(
                (string) $request->get_param('hash'),
                absint($request->get_param('size')),
                sanitize_file_name((string) $request->get_param('name')),
            ),
        ]);
    }

    public function inspect(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->answer($request, fn (): array => $this->desk->inspect(
            sanitize_file_name((string) $request->get_param('package')),
            get_current_user_id(),
        ));
    }

    public function prepare(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->answer($request, fn (): array => $this->installation->prepare(
            sanitize_file_name((string) $request->get_param('package')),
            get_current_user_id(),
            new DateTimeImmutable('now'),
        ));
    }

    public function confirm(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->answer($request, fn (): array => $this->installation->confirm(
            new ReleaseConfirmationGiven(
                sanitize_text_field((string) $request->get_param('phrase')),
                $request->get_param('client') === null ? null : sanitize_text_field((string) $request->get_param('client')),
                rest_sanitize_boolean($request->get_param('older') ?? false),
            ),
            get_current_user_id(),
            new DateTimeImmutable('now'),
        ));
    }

    public function step(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->answer($request, fn (): array => $this->installation->step(
            get_current_user_id(),
            new DateTimeImmutable('now'),
        ));
    }

    public function finish(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->answer($request, fn (): array => $this->completion->finish(
            get_current_user_id(),
            new DateTimeImmutable('now'),
        ));
    }

    public function cancel(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->answer($request, function (): array {
            $this->installation->cancel(get_current_user_id(), new DateTimeImmutable('now'));

            return ['cancelled' => true];
        });
    }

    private function route(string $path, string $method, string $callback): void
    {
        register_rest_route('corex/v1', $path, [
            'methods'             => $method,
            'callback'            => [$this, $callback],
            'permission_callback' => [$this->gateway, 'mayManage'],
        ]);
    }
}
