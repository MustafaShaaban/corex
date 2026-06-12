<?php

/**
 * Unit tests for the data REST controller's gating + payload shaping (spec 030: FR-004).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Data\DataController;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;

function fakeSource(string $key): DataSource
{
    return new class($key) implements DataSource {
        public function __construct(private string $k)
        {
        }

        public function key(): string
        {
            return $this->k;
        }

        public function label(): string
        {
            return 'X';
        }

        public function columns(): array
        {
            return [['id' => 'a', 'label' => 'A']];
        }

        public function rows(int $page, int $perPage): array
        {
            return [['a' => 'one']];
        }

        public function total(): int
        {
            return 1;
        }

        public function delete(int $id): bool
        {
            return $id === 7;
        }
    };
}

it('reads only for users who can manage options', function () {
    Functions\when('current_user_can')->alias(fn ($c) => $c === 'manage_options');

    expect((new DataController(new DataRegistry()))->canManage())->toBeTrue();

    Functions\when('current_user_can')->justReturn(false);
    expect((new DataController(new DataRegistry()))->canManage())->toBeFalse();
});

it('requires a valid REST nonce to delete', function () {
    Functions\when('wp_verify_nonce')->alias(fn ($nonce, $action) => $nonce === 'good' && $action === 'wp_rest' ? 1 : false);
    $controller = new DataController(new DataRegistry());

    expect($controller->verifiedNonce('good'))->toBeTrue()
        ->and($controller->verifiedNonce('bad'))->toBeFalse();
});

it('builds a {columns,rows,total} payload for a known source', function () {
    $registry = new DataRegistry();
    $registry->register(fakeSource('things'));

    $payload = (new DataController($registry))->payload('things', 1, 20);

    expect($payload['total'])->toBe(1)
        ->and($payload['rows'])->toBe([['a' => 'one']])
        ->and(array_column($payload['columns'], 'id'))->toBe(['a']);
});

it('returns null payload for an unknown source', function () {
    expect((new DataController(new DataRegistry()))->payload('nope', 1, 20))->toBeNull();
});
