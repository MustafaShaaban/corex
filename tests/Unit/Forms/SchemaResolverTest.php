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
            ['rule' => 'max_length', 'params' => ['80']],
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

/**
 * `max:N` and `min:N` used to measure whatever the answer looked like: `max:300` on a message
 * refused the answer `2025`, and `min:3` on a name accepted `12` (#250). What a bound measures is
 * settled here, from the field, so the server and the browser are handed the same rule.
 */
it('turns a bound on a field that is not a number into a length rule', function (array $definition) {
    $schema = resolver()->resolve(['f' => $definition + ['rules' => ['max:80', 'min:2']]]);

    expect($schema['f']->rules)->toBe([
        ['rule' => 'max_length', 'params' => ['80']],
        ['rule' => 'min_length', 'params' => ['2']],
    ]);
})->with([
    'no declared type' => [[]],
    'text' => [['type' => 'text']],
    'textarea' => [['type' => 'textarea']],
    'email' => [['type' => 'email']],
    'phone' => [['type' => 'phone']],
    'url' => [['type' => 'url']],
]);

it('leaves a bound on a number field comparing the number', function (array $definition) {
    $schema = resolver()->resolve(['f' => $definition]);

    expect(array_column($schema['f']->rules, 'rule'))->toContain('max', 'min')
        ->not->toContain('max_length', 'min_length');
})->with([
    'the number type' => [['type' => 'number', 'rules' => ['max:10', 'min:1']]],
    'the rating type' => [['type' => 'rating', 'rules' => ['max:5', 'min:1']]],
    'a text field that declares numeric first' => [['type' => 'text', 'rules' => ['numeric', 'max:10', 'min:1']]],
    'a text field that declares numeric last' => [['type' => 'text', 'rules' => ['max:10', 'min:1', 'numeric']]],
]);

it('leaves a length rule on a number field counting characters', function () {
    $schema = resolver()->resolve(['pin' => ['type' => 'number', 'rules' => ['max_length:6', 'min_length:4']]]);

    expect(array_column($schema['pin']->rules, 'rule'))->toBe(['max_length', 'min_length']);
});
