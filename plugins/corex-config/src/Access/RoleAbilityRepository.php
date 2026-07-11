<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AccessPolicy;
use Corex\Access\RoleAbilityStore;
use Corex\Database\Schema\Migrator;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Persists explicit CoreX-only role ability effects.
 */
final class RoleAbilityRepository implements RoleAbilityStore
{
    private const SOURCE = 'explicit';

    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function effectsForRole(string $roleKey): array
    {
        global $wpdb;

        $this->assertRole($roleKey);
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT ability_key, effect FROM ' . $this->table() . ' WHERE role_key = %s AND source = %s',
            $roleKey,
            self::SOURCE,
        ), ARRAY_A);

        $effects = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $effects[(string) $row['ability_key']] = (string) $row['effect'];
        }

        return $effects;
    }

    public function apply(string $roleKey, array $changes, int $actorId, DateTimeImmutable $updatedAt): void
    {
        global $wpdb;

        $this->assertRole($roleKey);

        if ($actorId < 1) {
            throw new InvalidArgumentException('Role ability changes require an authenticated actor.');
        }

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($changes as $abilityKey => $effect) {
                $this->persist($roleKey, $abilityKey, $effect, $actorId, $updatedAt);
            }

            $wpdb->query('COMMIT');
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    private function persist(
        string $roleKey,
        string $abilityKey,
        string $effect,
        int $actorId,
        DateTimeImmutable $updatedAt,
    ): void {
        global $wpdb;

        if (preg_match('/^corex_[a-z0-9_]+$/', $abilityKey) !== 1) {
            throw new InvalidArgumentException('Only CoreX-owned abilities can be persisted.');
        }

        if ($effect === AccessPolicy::EFFECT_INHERIT) {
            $deleted = $wpdb->delete($this->table(), [
                'role_key'   => $roleKey,
                'ability_key' => $abilityKey,
                'source'     => self::SOURCE,
            ]);

            if ($deleted === false) {
                throw new RuntimeException('CoreX could not remove the explicit role ability effect.');
            }

            return;
        }

        if (! in_array($effect, [AccessPolicy::EFFECT_ALLOW, AccessPolicy::EFFECT_DENY], true)) {
            throw new InvalidArgumentException('Unsupported explicit role ability effect.');
        }

        $result = $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . $this->table()
            . ' (role_key, ability_key, effect, source, updated_by, updated_at) VALUES (%s, %s, %s, %s, %d, %s) '
            . 'ON DUPLICATE KEY UPDATE effect = VALUES(effect), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
            $roleKey,
            $abilityKey,
            $effect,
            self::SOURCE,
            $actorId,
            $updatedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ));

        if ($result === false) {
            throw new RuntimeException('CoreX could not persist the role ability effect.');
        }
    }

    private function assertRole(string $roleKey): void
    {
        if (preg_match('/^[a-z0-9_-]+$/', $roleKey) !== 1) {
            throw new InvalidArgumentException('Role key is invalid.');
        }
    }

    private function table(): string
    {
        return $this->migrator->fullName(AccessTables::ROLE_GRANTS);
    }
}
