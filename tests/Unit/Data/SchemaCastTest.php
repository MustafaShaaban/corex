<?php

/**
 * Unit tests for the custom-table schema builder + caster (spec 011 US1+US2: FR-001, FR-004, SC-001/2/5).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Database\Casts\Caster;
use Corex\Database\Schema\Table;

it('generates a prefixed CREATE TABLE with each column and a primary key', function () {
    $sql = (new Table('subscribers'))
        ->id()
        ->string('email')
        ->boolean('confirmed')
        ->integer('topic_count')
        ->datetime('confirmed_at', nullable: true)
        ->timestamps()
        ->createSql('wp_corex_subscribers', 'DEFAULT CHARSET=utf8mb4');

    expect($sql)->toContain('CREATE TABLE wp_corex_subscribers')
        ->toContain('id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT')
        ->toContain('email VARCHAR(255) NOT NULL')
        ->toContain('confirmed TINYINT(1) NOT NULL')
        ->toContain('topic_count BIGINT NOT NULL')
        ->toContain('confirmed_at DATETIME NULL')
        ->toContain('created_at DATETIME NULL')
        ->toContain('PRIMARY KEY  (id)')
        ->toContain('DEFAULT CHARSET=utf8mb4');
});

it('casts each supported type to its PHP value on hydrate', function () {
    $caster = new Caster();

    expect($caster->toPhp('5', 'int'))->toBe(5)
        ->and($caster->toPhp('1', 'bool'))->toBeTrue()
        ->and($caster->toPhp('0', 'bool'))->toBeFalse()
        ->and($caster->toPhp('9.95', 'decimal'))->toBe(9.95)
        ->and($caster->toPhp('[1,2,3]', 'json'))->toBe([1, 2, 3])
        ->and($caster->toPhp('not-json', 'json'))->toBe([])           // malformed → empty array
        ->and($caster->toPhp('2026-06-09 10:00:00', 'datetime'))->toBeInstanceOf(DateTimeImmutable::class);
});

it('serializes each supported type to a storable scalar on persist', function () {
    $caster = new Caster();

    expect($caster->toStore([1, 2], 'json'))->toBe('[1,2]')
        ->and($caster->toStore(true, 'bool'))->toBe(1)
        ->and($caster->toStore(false, 'bool'))->toBe(0)
        ->and($caster->toStore(new DateTimeImmutable('2026-06-09 10:00:00'), 'datetime'))->toBe('2026-06-09 10:00:00');
});
