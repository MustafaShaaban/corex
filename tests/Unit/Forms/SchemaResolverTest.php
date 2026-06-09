<?php

/**
 * Unit tests for the form-schema resolver (spec US1: FR-005, FR-018).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Schema\FieldSchema;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Validation\RuleRegistry;

function resolver(): SchemaResolver
{
    return new SchemaResolver(new RuleRegistry());
}

it('resolves a definition into a FieldSchema map with required derived', function () {
    $schema = resolver()->resolve([
        'name'  => ['type' => 'text', 'rules' => ['required', 'max:80'], 'label' => 'Your name'],
        'email' => ['rules' => ['email']],
    ]);

    expect($schema)->toHaveKeys(['name', 'email'])
        ->and($schema['name'])->toBeInstanceOf(FieldSchema::class)
        ->and($schema['name']->required)->toBeTrue()
        ->and($schema['name']->label)->toBe('Your name')
        ->and($schema['name']->type)->toBe('text')
        ->and($schema['name']->rules)->toBe([
            ['rule' => 'required', 'params' => []],
            ['rule' => 'max', 'params' => ['80']],
        ])
        ->and($schema['email']->required)->toBeFalse()
        ->and($schema['email']->type)->toBe('text'); // default type
});

it('throws when two field names normalize to the same canonical key', function () {
    resolver()->resolve([
        'Email' => ['rules' => ['email']],
        'email' => ['rules' => ['email']], // distinct PHP keys, both normalize to "email"
    ]);
})->throws(InvalidArgumentException::class);

it('throws on an unknown rule name', function () {
    resolver()->resolve([
        'name' => ['rules' => ['definitely_not_a_rule']],
    ]);
})->throws(InvalidArgumentException::class);
