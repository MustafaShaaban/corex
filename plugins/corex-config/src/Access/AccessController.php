<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Operations\Confirmation;
use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST boundary for CoreX-owned abilities and access-request workflows.
 */
final class AccessController
{
    private const NAMESPACE = 'corex/v1';

    public function __construct(
        private readonly AccessService $service,
        private readonly CorexAbilityCatalog $catalog,
    ) {
    }

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/access/catalog', [
            'methods' => 'GET',
            'callback' => [$this, 'catalog'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/access/roles/(?P<role>[\w-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'role'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/access/roles/(?P<role>[\w-]+)/preview', [
            'methods' => 'POST',
            'callback' => [$this, 'previewRole'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/access/roles/(?P<role>[\w-]+)/apply', [
            'methods' => 'POST',
            'callback' => [$this, 'applyRole'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/access/requests', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'requests'],
            'permission_callback' => [$this, 'requestAllowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/access/requests/(?P<id>\d+)/decision', [
            'methods' => 'POST',
            'callback' => [$this, 'decideRequest'],
            'permission_callback' => [$this, 'allowed'],
        ]);
    }

    public function allowed(WP_REST_Request $request): bool
    {
        return $this->hasNonce($request) && current_user_can('manage_options');
    }

    public function requestAllowed(WP_REST_Request $request): bool
    {
        return $this->hasNonce($request) && is_user_logged_in();
    }

    public function catalog(WP_REST_Request $request): WP_REST_Response
    {
        return $this->ok([
            'abilities' => array_map($this->ability(...), $this->catalog->all()),
        ]);
    }

    public function role(WP_REST_Request $request): WP_REST_Response
    {
        $role = sanitize_key((string) $request->get_param('role'));

        return $this->ok([
            'role' => $role,
            'target_hash' => $this->service->roleChangeHash($role, []),
        ]);
    }

    public function previewRole(WP_REST_Request $request): WP_REST_Response
    {
        $role = sanitize_key((string) $request->get_param('role'));
        $changes = $this->changes($request);
        $preview = $this->service->previewRoleChanges(get_current_user_id(), $role, $changes);

        return $this->ok([
            'allowed' => $preview->allowed,
            'blockers' => $preview->blockers,
            'changes' => $preview->changes,
            'target_hash' => $this->service->roleChangeHash($role, $changes),
        ]);
    }

    public function applyRole(WP_REST_Request $request): WP_REST_Response
    {
        $role = sanitize_key((string) $request->get_param('role'));
        $result = $this->service->changeRoleAbilities(
            get_current_user_id(),
            $role,
            $this->changes($request),
            $this->confirmation((array) $request->get_param('confirmation')),
            new DateTimeImmutable('now'),
        );

        return $this->ok(['result' => $result->toArray()]);
    }

    public function requests(WP_REST_Request $request): WP_REST_Response
    {
        if ($request->get_method() === 'POST') {
            return $this->createRequest($request);
        }

        return $this->ok(['requests' => []]);
    }

    public function createRequest(WP_REST_Request $request): WP_REST_Response
    {
        $now = new DateTimeImmutable('now');
        $result = $this->service->requestAccess(
            get_current_user_id(),
            $this->optionalString($request->get_param('ability')),
            $this->optionalString($request->get_param('area')),
            sanitize_textarea_field((string) $request->get_param('reason')),
            $now,
            $now->modify('+7 days'),
        );

        return $this->ok(['result' => $result->toArray()]);
    }

    public function decideRequest(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->service->decideRequest(
            get_current_user_id(),
            (int) $request->get_param('id'),
            (bool) $request->get_param('approved'),
            sanitize_textarea_field((string) $request->get_param('note')),
            new DateTimeImmutable('now'),
        );

        return $this->ok(['result' => $result->toArray()]);
    }

    private function hasNonce(WP_REST_Request $request): bool
    {
        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '') {
            $nonce = (string) $request->get_param('_wpnonce');
        }

        return wp_verify_nonce($nonce, 'wp_rest') !== false;
    }

    /** @return array<string,string> */
    private function changes(WP_REST_Request $request): array
    {
        $changes = $request->get_param('changes');
        if (! is_array($changes)) {
            return [];
        }

        $normalized = [];
        foreach ($changes as $ability => $effect) {
            $normalized[sanitize_key((string) $ability)] = sanitize_key((string) $effect);
        }

        return $normalized;
    }

    /** @param array<string,mixed> $payload */
    private function confirmation(array $payload): Confirmation
    {
        return new Confirmation(
            operationKind: (string) ($payload['operation_kind'] ?? ''),
            targetHash: (string) ($payload['target_hash'] ?? ''),
            actorId: (int) ($payload['actor_id'] ?? 0),
            expiresAt: new DateTimeImmutable((string) ($payload['expires_at'] ?? 'now')),
            requiredPhrase: $this->optionalString($payload['required_phrase'] ?? null),
            usedAt: $this->optionalDate($payload['used_at'] ?? null),
        );
    }

    private function optionalString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : sanitize_key($value);
    }

    private function optionalDate(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && trim($value) !== '' ? new DateTimeImmutable($value) : null;
    }

    /** @return array<string,mixed> */
    private function ability(CorexAbility $ability): array
    {
        return [
            'key' => $ability->key,
            'label' => $ability->label,
            'group' => $ability->group,
            'risk' => $ability->risk,
            'locked' => $ability->locked,
        ];
    }

    /** @param array<string,mixed> $data */
    private function ok(array $data): WP_REST_Response
    {
        return new WP_REST_Response(['data' => $data], 200);
    }
}
