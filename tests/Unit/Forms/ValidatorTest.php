<?php

/**
 * Unit tests for the headless validator (spec US1: FR-002, FR-003, SC-002, SC-006).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;

/**
 * @param array<string,array{type?:string,rules?:list<string>,label?:string}> $fields
 * @param array<string,mixed>                                                  $values
 */
function validate(array $fields, array $values): \Corex\Forms\Validation\ValidationResult
{
    $registry = new RuleRegistry();
    $schema   = (new SchemaResolver($registry))->resolve($fields);

    return (new Validator($registry))->validate($schema, $values);
}

it('passes every rule on good input and yields no errors', function () {
    $result = validate(
        ['name' => ['rules' => ['required', 'max:80']], 'email' => ['rules' => ['email']], 'age' => ['rules' => ['numeric', 'min:18']]],
        ['name' => 'Mustafa', 'email' => 'm@example.com', 'age' => '21'],
    );

    expect($result->isValid())->toBeTrue()
        ->and($result->errors)->toBe([]);
});

it('returns the exact message key for each failing rule', function () {
    expect(validate(['f' => ['rules' => ['required']]], ['f' => ''])->errors)->toBe(['f' => 'required']);
    expect(validate(['f' => ['rules' => ['email']]], ['f' => 'nope'])->errors)->toBe(['f' => 'email']);
    expect(validate(['f' => ['rules' => ['max:3']]], ['f' => 'toolong'])->errors)->toBe(['f' => 'max']);
    expect(validate(['f' => ['rules' => ['min:3']]], ['f' => 'ab'])->errors)->toBe(['f' => 'min']);
    expect(validate(['f' => ['rules' => ['numeric']]], ['f' => 'x'])->errors)->toBe(['f' => 'numeric']);
});

it('bails at the first failing rule per field, honoring rule order', function () {
    // empty value: 'required' fails before 'email' is reached → only 'required'.
    $result = validate(['email' => ['rules' => ['required', 'email']]], ['email' => '']);

    expect($result->errors)->toBe(['email' => 'required']);
});

it('treats an absent optional field as valid', function () {
    $result = validate(['nickname' => ['rules' => ['max:20']]], []);

    expect($result->isValid())->toBeTrue();
});

it('reports a required field that is absent', function () {
    $result = validate(['name' => ['rules' => ['required']]], []);

    expect($result->errors)->toBe(['name' => 'required']);
});

it('ignores values whose field is not declared in the schema', function () {
    $result = validate(['name' => ['rules' => ['required']]], ['name' => 'A', 'sneaky' => 'x']);

    expect($result->isValid())->toBeTrue()
        ->and($result->values)->toBe(['name' => 'A']); // undeclared field dropped
});

/**
 * `max_length` and `min_length` were registered as the same classes as `max` and `min`, which
 * compare an all-digit answer as a number. A phone field limited to 32 characters refused
 * `01016999700` as too long, and a two-digit answer satisfied a three-character minimum.
 * Reported on 2026-10-06 from a client contact form.
 */
it('counts characters for a length rule when the answer is all digits', function (string $rule, string $value, array $errors) {
    expect(validate(['f' => ['rules' => [$rule]]], ['f' => $value])->errors)->toBe($errors);
})->with([
    'a phone number inside its character limit' => ['max_length:32', '01016999700', []],
    'digits exactly at the character limit' => ['max_length:11', '01016999700', []],
    'digits past the character limit' => ['max_length:5', '01016999700', ['f' => 'max']],
    'digits short of the character minimum' => ['min_length:3', '12', ['f' => 'min']],
    'digits that reach the character minimum' => ['min_length:3', '007', []],
]);

it('accepts a contact message that is only a number', function () {
    // The stock form translates its labels where it declares them.
    Brain\Monkey\Functions\stubTranslationFunctions();

    $result = validate(
        (new Corex\Forms\Forms\ContactForm())->fields(),
        ['name' => 'Mustafa', 'email' => 'm@example.com', 'message' => '2025'],
    );

    expect($result->errors)->toBe([]);
});

/**
 * `max` and `min` decided what to measure from what the answer looked like, so a message bounded
 * by `max:300` refused `2025` and a name bounded by `min:3` accepted `12` (#250). They now follow
 * the field: a number field compares the number, every other field counts characters.
 */
it('measures a bound by what the field is for', function (array $definition, string $value, array $errors) {
    expect(validate(['f' => $definition], ['f' => $value])->errors)->toBe($errors);
})->with([
    'a message that is only a year' => [['type' => 'textarea', 'rules' => ['max:300']], '2025', []],
    'a two-digit name under a three-character minimum' => [['type' => 'text', 'rules' => ['min:3']], '12', ['f' => 'min']],
    'a phone number typed into a text field' => [['rules' => ['max:20']], '01016999700', []],
    'digits past a text field’s character limit' => [['rules' => ['max:5']], '123456', ['f' => 'max']],
    'a number over its limit' => [['type' => 'number', 'rules' => ['max:10']], '11', ['f' => 'max']],
    'a number at its limit' => [['type' => 'number', 'rules' => ['max:10']], '10', []],
    'a long number under its limit' => [['type' => 'number', 'rules' => ['max:10']], '9.5000', []],
    'an age under its minimum' => [['rules' => ['numeric', 'min:18']], '17', ['f' => 'min']],
    'an age at its minimum' => [['rules' => ['numeric', 'min:18']], '18', []],
    'a rating over its scale' => [['type' => 'rating', 'rules' => ['max:5']], '6', ['f' => 'max']],
]);

it('counts characters for a quantity that is declared only as text', function () {
    // The change a form owes itself: a field that means a number says so, with the `number` type
    // or the `numeric` rule. Until it does, `99999` is five characters.
    expect(validate(['quantity' => ['type' => 'text', 'rules' => ['max:10']]], ['quantity' => '99999'])->errors)
        ->toBe([]);
});
