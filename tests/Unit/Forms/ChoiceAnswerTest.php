<?php

/**
 * A choice field takes only the answers it offers.
 *
 * A `select`, `radio`, `multi-select` or `checkbox-group` declares its options, and until this
 * nothing compared the answer with them: the browser only offers what is declared, and a request
 * written by hand could store any string as the visitor's choice. Reported on 2026-10-08 from a
 * client site, whose form routes an enquiry by the option chosen.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;

/**
 * @param array<string,mixed> $definition the one field, named `answer`
 *
 * @return array<string,string> the errors
 */
function choiceErrors(array $definition, mixed $answer): array
{
    $registry = new RuleRegistry();
    $schema   = (new SchemaResolver($registry))->resolve(['answer' => $definition]);

    return (new Validator($registry))->validate($schema, ['answer' => $answer])->errors;
}

const CONTACT_BY = ['email' => 'Email', 'phone' => 'Phone'];

it('takes an answer the field offers', function (string $type, mixed $answer) {
    expect(choiceErrors(['type' => $type, 'options' => CONTACT_BY], $answer))->toBe([]);
})->with([
    'a radio' => ['radio', 'phone'],
    'a select' => ['select', 'email'],
    'every box ticked in a group' => ['checkbox-group', ['email', 'phone']],
    'one of several in a multiple select' => ['multi-select', ['phone']],
    // One selection can arrive as a plain value: a group with a single box, or a control swapped
    // for a single select.
    'a group answered with one plain value' => ['checkbox-group', 'email'],
]);

it('refuses an answer the field never offered', function (string $type, mixed $answer) {
    expect(choiceErrors(['type' => $type, 'options' => CONTACT_BY], $answer))->toBe(['answer' => 'choice']);
})->with([
    'a radio' => ['radio', 'fax'],
    'a select' => ['select', 'Email'],
    'one stray value among offered ones' => ['checkbox-group', ['email', 'fax']],
    'a multiple select' => ['multi-select', ['pigeon']],
    // A single-choice field has one answer. Two offered values are still not an answer to it.
    'a list sent to a radio' => ['radio', ['email', 'phone']],
    'something that is not a value at all' => ['checkbox-group', [['email']]],
]);

it('leaves an unanswered choice to the required rule', function (array $rules, mixed $answer, array $errors) {
    expect(choiceErrors(['type' => 'checkbox-group', 'options' => CONTACT_BY, 'rules' => $rules], $answer))
        ->toBe($errors);
})->with([
    'optional, nothing ticked' => [[], [], []],
    'optional, an empty value' => [[], '', []],
    'required, nothing ticked' => [['required'], [], ['answer' => 'required']],
]);

/**
 * PHP turns the array key `'2025'` into the integer 2025, and the answer arrives as a string.
 */
it('takes an offered answer that is a number', function () {
    expect(choiceErrors(['type' => 'select', 'options' => ['2024', '2025']], '2025'))->toBe([])
        ->and(choiceErrors(['type' => 'select', 'options' => ['2024', '2025']], '2026'))->toBe(['answer' => 'choice']);
});

/**
 * A field whose options are put there by the theme's own script declares none. There is nothing to
 * compare with, and refusing every answer would break the form.
 */
it('compares nothing when the field declares no options', function () {
    expect(choiceErrors(['type' => 'select'], 'anything'))->toBe([]);
});

it('does not treat a text field as a choice because it was given options', function () {
    expect(choiceErrors(['type' => 'text', 'options' => CONTACT_BY], 'fax'))->toBe([]);
});
