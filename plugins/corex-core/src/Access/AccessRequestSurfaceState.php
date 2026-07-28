<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

/**
 * What the denied surface needs to know to decide which request state to render (spec 079).
 *
 * Declared where it is consumed (the admin surface in corex-core) rather than beside the repository
 * that implements it (corex-config), per the dependency-inversion rule in the constitution.
 *
 * Both methods answer about the signed-in user and take no user ID. That is the point: a
 * presentation class that could pass a user ID would be a presentation class making an
 * authorization decision, and the denied surface is also embedded as an administrator-facing
 * preview — one wrong argument there would show an administrator somebody else's state.
 */
interface AccessRequestSurfaceState
{
    /** The reason was rejected; the typed text comes back so it is not lost. */
    public const PROBLEM_INVALID = 'invalid';

    /** CoreX failed, not the requester. Nothing was recorded and retrying is safe. */
    public const PROBLEM_FAILED = 'failed';

    /** The current user's own open request for this ability, or null. */
    public function pendingForCurrentUser(string $abilityKey): ?PendingAccessRequest;

    /**
     * A problem left over from the current user's last submission, read once and consumed.
     *
     * @return array{problem:string,reason:string,reference:string}|null
     */
    public function problemForCurrentUser(): ?array;
}
