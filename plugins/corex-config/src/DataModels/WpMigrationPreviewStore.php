<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

/** Actor-bound five-minute single-use migration confirmations. */
final class WpMigrationPreviewStore implements MigrationPreviewStore
{
    private const TTL = 300;

    public function issue(
        int $actorId,
        string $action,
        string $sourceKey,
        MigrationDefinition $definition,
        int $runId = 0,
    ): MigrationPreview {
        $token = wp_generate_password(32, false, false);
        $preview = MigrationPreview::from([
            'token' => $token, 'actor_id' => $actorId, 'action' => $action,
            'source_key' => $sourceKey, 'definition' => $definition->toArray(),
            'run_id' => $runId, 'expires_at' => time() + self::TTL,
        ]);
        set_transient($this->key($token), $preview->toArray(), self::TTL);

        return $preview;
    }

    public function consume(string $token, int $actorId): ?MigrationPreview
    {
        $key = $this->key($token);
        $payload = get_transient($key);
        delete_transient($key);
        if (! is_array($payload)) {
            return null;
        }
        $preview = MigrationPreview::from($payload);

        return $preview->actorId === $actorId && ! $preview->expired(time()) ? $preview : null;
    }

    private function key(string $token): string
    {
        return 'corex_migration_preview_' . hash('sha256', $token);
    }
}
