<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;

/**
 * The Releases screen's routes, up to the point of knowing what a package is (spec 107, slice 3).
 *
 *     GET  releases                         what is installed, what the host cannot do, the packages here
 *     GET  releases/uploads/{hash}          how much of an upload the site holds
 *     POST releases/uploads/{hash}          a part of a package, as the request's body
 *     POST releases/uploads/{hash}/complete all of it has been sent: keep it, if it is what was sent
 *     POST releases/inspect                 what a package on the site is, or why it is refused
 *
 * None of them changes a file of the site outside `wp-content/corex-releases/`.
 */
final class ReleasesController
{
    private const HASH = '(?P<hash>[0-9a-f]{64})';

    public function __construct(
        private readonly ReleaseRestGateway $gateway,
        private readonly ReleaseDesk $desk,
        private readonly ReleaseUpload $upload,
    ) {
    }

    public function register(): void
    {
        $this->route('/releases', 'GET', 'overview');
        $this->route('/releases/uploads/' . self::HASH, 'GET', 'received');
        $this->route('/releases/uploads/' . self::HASH, 'POST', 'append');
        $this->route('/releases/uploads/' . self::HASH . '/complete', 'POST', 'complete');
        $this->route('/releases/inspect', 'POST', 'inspect');
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

    private function route(string $path, string $method, string $callback): void
    {
        register_rest_route('corex/v1', $path, [
            'methods'             => $method,
            'callback'            => [$this, $callback],
            'permission_callback' => [$this->gateway, 'mayManage'],
        ]);
    }
}
