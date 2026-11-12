<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AbilityCheck;
use Corex\Access\AbilityNames;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\InvalidAccessInput;
use Corex\Http\RouteParam;
use Corex\Operations\Confirmation;
use DateMalformedStringException;
use DateTimeImmutable;
use InvalidArgumentException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST boundary for CoreX-owned abilities and access-request workflows.
 *
 * Two kinds of "no" leave here. A request that cannot be read (a parameter missing, of the wrong
 * shape, or naming something CoreX does not have) is answered 400 and names the parameter: it is
 * the same mistake however often it is sent. A request that can be read and that the service
 * refuses for how things stand (a decision on a request no longer pending, a change the safety
 * policy stops, a confirmation that confirms something else) is answered 200 with a result whose
 * state is `blocked`.
 */
final class AccessController
{
    private const NAMESPACE = 'corex/v1';

    public function __construct(
        private readonly AccessService $service,
        private readonly CorexAbilityCatalog $catalog,
        private readonly PendingAccessRequests $pending,
        private readonly AccessGrantService $grants,
        private readonly AccessGrantPresenter $presenter,
        private readonly AbilityCheck $abilities = new AbilityCheck(),
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
        // One route, two questions, and a check for each: anybody signed in may ask for access,
        // and the queue of what was asked is for the people who decide it.
        register_rest_route(self::NAMESPACE, '/access/requests', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'pendingRequests'],
                'permission_callback' => [$this, 'allowed'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createRequest'],
                'permission_callback' => [$this, 'requestAllowed'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/access/requests/(?P<id>\d+)/preview', [
            'methods' => 'POST',
            'callback' => [$this, 'previewDecision'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/access/requests/(?P<id>\d+)/decision', [
            'methods' => 'POST',
            'callback' => [$this, 'decideRequest'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        // Spec 112: the grants made by approval, with the request list's check (DECISIONS #346).
        register_rest_route(self::NAMESPACE, '/access/grants', [
            'methods' => 'GET',
            'callback' => [$this, 'standingGrants'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/access/grants/(?P<id>\d+)/revoke', [
            'methods' => 'POST',
            'callback' => [$this, 'revokeGrant'],
            'permission_callback' => [$this, 'allowed'],
        ]);
    }

    /** The Access screen's own ability (spec 117, FR-002). */
    public function allowed(WP_REST_Request $request): bool
    {
        return $this->hasNonce($request) && $this->abilities->allows(CorexAbility::MANAGE_ACCESS, 'corex-access');
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
        return $this->answer(function () use ($request): array {
            $role = $this->roleKey($request);

            return [
                'role' => $role,
                'target_hash' => $this->service->roleChangeHash($role, []),
            ];
        });
    }

    public function previewRole(WP_REST_Request $request): WP_REST_Response
    {
        return $this->answer(function () use ($request): array {
            $role = $this->roleKey($request);
            $changes = $this->changes($request);
            $preview = $this->service->previewRoleChanges(get_current_user_id(), $role, $changes);

            return [
                'allowed' => $preview->allowed,
                'blockers' => $preview->blockers,
                'changes' => $preview->changes,
                'target_hash' => $this->service->roleChangeHash($role, $changes),
            ];
        });
    }

    public function applyRole(WP_REST_Request $request): WP_REST_Response
    {
        return $this->answer(function () use ($request): array {
            $result = $this->service->changeRoleAbilities(
                get_current_user_id(),
                $this->roleKey($request),
                $this->changes($request),
                $this->confirmation((array) $request->get_param('confirmation')),
                new DateTimeImmutable('now'),
            );

            return ['result' => $result->toArray()];
        });
    }

    public function pendingRequests(WP_REST_Request $request): WP_REST_Response
    {
        return $this->ok(['requests' => $this->pending->all()]);
    }

    public function createRequest(WP_REST_Request $request): WP_REST_Response
    {
        return $this->answer(function () use ($request): array {
            $now = new DateTimeImmutable('now');
            $result = $this->service->requestAccess(
                get_current_user_id(),
                $this->optionalString($request->get_param('ability')),
                $this->optionalString($request->get_param('area')),
                sanitize_textarea_field($this->text($request->get_param('reason'))),
                $now,
                $now->modify('+7 days'),
            );

            return ['result' => $result->toArray()];
        });
    }

    public function previewDecision(WP_REST_Request $request): WP_REST_Response
    {
        $preview = $this->service->previewApproval(get_current_user_id(), RouteParam::int($request));

        return $this->ok(['preview' => $preview === null ? null : $this->presenter->preview($preview)]);
    }

    /**
     * Approval needs a note, an expiry and a confirmation (spec 112, FR-002); a refusal does not.
     */
    public function decideRequest(WP_REST_Request $request): WP_REST_Response
    {
        return $this->answer(function () use ($request): array {
            $id   = RouteParam::int($request);
            $note = sanitize_textarea_field($this->text($request->get_param('note')));
            $now  = new DateTimeImmutable('now');

            $result = (bool) $request->get_param('approved')
                ? $this->service->approveRequest(
                    get_current_user_id(),
                    $id,
                    $note,
                    (int) $this->text($request->get_param('expiry_days')),
                    $this->confirmation((array) $request->get_param('confirmation')),
                    $now,
                )
                : $this->service->denyRequest(get_current_user_id(), $id, $note, $now);

            return ['result' => $result->toArray()];
        });
    }

    public function standingGrants(WP_REST_Request $request): WP_REST_Response
    {
        return $this->ok(['grants' => $this->presenter->grants()]);
    }

    public function revokeGrant(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->grants->revoke(get_current_user_id(), RouteParam::int($request), new DateTimeImmutable('now'));

        return $this->ok(['result' => $result->toArray()]);
    }

    /**
     * Runs one request's work and answers with what it returns, or with a 400 when the work
     * refuses what was sent.
     *
     * Only `InvalidAccessInput` is answered here. Anything else thrown is CoreX's failure and is
     * left to WordPress to answer as one: a 400 would tell the caller to correct a request that
     * had nothing wrong with it.
     *
     * @param callable():array<string,mixed> $work
     */
    private function answer(callable $work): WP_REST_Response
    {
        try {
            return $this->ok($work());
        } catch (InvalidAccessInput $refused) {
            return $this->refused($refused);
        }
    }

    /**
     * The body WordPress itself answers for a parameter it refuses (`rest_invalid_param`), so a
     * client that reads one reads the other. The refusal's own code is under `data.details`.
     */
    private function refused(InvalidAccessInput $refused): WP_REST_Response
    {
        $message = $this->refusalMessage($refused->refusal);

        return new WP_REST_Response([
            'code' => 'rest_invalid_param',
            'message' => $message,
            'data' => [
                'status' => 400,
                'params' => [$refused->parameter => $message],
                'details' => [
                    $refused->parameter => ['code' => $refused->refusal, 'message' => $message, 'data' => null],
                ],
            ],
        ], 400);
    }

    private function refusalMessage(string $refusal): string
    {
        return match ($refusal) {
            // The words the form on a denied screen shows for the same refusal.
            InvalidAccessInput::REASON_REQUIRED => __('Tell the administrator why you need access — a request with no reason cannot be judged.', 'corex'),
            InvalidAccessInput::REASON_TOO_LONG => sprintf(
                /* translators: %s: the longest reason an access request may carry, in characters. */
                _n(
                    'The reason is longer than %s character. Shorten it and send the request again.',
                    'The reason is longer than %s characters. Shorten it and send the request again.',
                    AccessService::MAX_REASON_LENGTH,
                    'corex',
                ),
                number_format_i18n(AccessService::MAX_REASON_LENGTH),
            ),
            InvalidAccessInput::TARGET_REQUIRED => __('Name the ability or the area the request is for.', 'corex'),
            InvalidAccessInput::TARGET_AMBIGUOUS => __('Name an ability or an area, not both.', 'corex'),
            InvalidAccessInput::ABILITY_UNKNOWN => __('That is not an ability CoreX has.', 'corex'),
            InvalidAccessInput::AREA_UNKNOWN => __('That is not an area CoreX has.', 'corex'),
            InvalidAccessInput::ABILITY_NOT_REQUESTABLE => __('An administrator grants this ability through the role matrix. It cannot be requested.', 'corex'),
            InvalidAccessInput::AREA_NOT_REQUESTABLE => __('This area holds an ability an administrator grants through the role matrix. It cannot be requested.', 'corex'),
            InvalidAccessInput::ROLE_INVALID => __('That is not a role key.', 'corex'),
            InvalidAccessInput::EFFECT_UNSUPPORTED => __('An ability is set to allow, deny or inherit.', 'corex'),
            InvalidAccessInput::CONFIRMATION_MALFORMED => __('The confirmation could not be read. Preview the change again, then apply it.', 'corex'),
            // A refusal given no words of its own is still the caller's mistake, and still a 400.
            default => __('The request could not be read. Nothing was changed.', 'corex'),
        };
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
            $normalized[sanitize_key((string) $ability)] = sanitize_key($this->text($effect));
        }

        return $normalized;
    }

    private function roleKey(WP_REST_Request $request): string
    {
        return sanitize_key($this->text($request->get_param('role')));
    }

    /**
     * The confirmation as it was sent, or a refusal when it cannot be one.
     *
     * A confirmation that can be read and confirms something else is the service's to refuse, and
     * it answers that one `blocked`.
     *
     * @param array<array-key,mixed> $payload
     */
    private function confirmation(array $payload): Confirmation
    {
        try {
            return new Confirmation(
                operationKind: $this->text($payload['operation_kind'] ?? ''),
                targetHash: $this->text($payload['target_hash'] ?? ''),
                actorId: (int) $this->text($payload['actor_id'] ?? ''),
                expiresAt: new DateTimeImmutable($this->text($payload['expires_at'] ?? 'now')),
                requiredPhrase: $this->optionalString($payload['required_phrase'] ?? null),
                usedAt: $this->optionalDate($payload['used_at'] ?? null),
            );
        } catch (InvalidArgumentException|DateMalformedStringException $unreadable) {
            // The value object's own refusals, and PHP's for a time it cannot parse.
            throw new InvalidAccessInput('confirmation', InvalidAccessInput::CONFIRMATION_MALFORMED, $unreadable->getMessage());
        }
    }

    /**
     * A parameter as text, or as nothing when it is not text: a list read as text is the word
     * "Array" and a PHP warning.
     */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
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
        // Names in the site's language; the key stays for developers (spec 117, US-ACC-05).
        return [
            'key' => $ability->key,
            'label' => AbilityNames::labelOf($ability),
            'description' => AbilityNames::descriptionOf($ability),
            'group' => $ability->group,
            'group_label' => AbilityNames::area($ability->group),
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
