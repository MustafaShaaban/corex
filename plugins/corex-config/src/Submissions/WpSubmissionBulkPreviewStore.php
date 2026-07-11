<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Single-use transient store for actor-bound bulk confirmations.
 */
final class WpSubmissionBulkPreviewStore implements SubmissionBulkPreviewStore
{
    private const TTL = 300;

    public function issue(int $actorId, string $action, array $records, array $parameters): SubmissionBulkPreview
    {
        $token = wp_generate_password(32, false, false);
        $preview = SubmissionBulkPreview::from([
            'token' => $token,
            'actor_id' => $actorId,
            'action' => $action,
            'records' => $records,
            'parameters' => $parameters,
            'expires_at' => time() + self::TTL,
        ]);
        set_transient($this->key($token), $preview->toArray(), self::TTL);

        return $preview;
    }

    public function consume(string $token, int $actorId): ?SubmissionBulkPreview
    {
        $key = $this->key($token);
        $payload = get_transient($key);
        delete_transient($key);
        if (! is_array($payload)) {
            return null;
        }

        $preview = SubmissionBulkPreview::from($payload);
        if ($preview->actorId !== $actorId || $preview->expired(time())) {
            return null;
        }

        return $preview;
    }

    private function key(string $token): string
    {
        return 'corex_submission_bulk_' . hash('sha256', $token);
    }
}
