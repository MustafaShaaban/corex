<?php

/**
 * A `pattern:` rule is one expression, whatever characters it has in it.
 *
 * A rule is written `name:parameters` and the parameters were split on every comma, which is right
 * for `mime:application/pdf,image/png` and cut `pattern:^\d{2,4}$` into `^\d{2` and `4}$`. The
 * first half is not an expression, so the rule refused every answer.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;

/**
 * @return array<string,string> the errors
 */
function patternErrors(string $rule, string $answer): array
{
    $registry = new RuleRegistry();
    $schema   = (new SchemaResolver($registry))->resolve(['code' => ['rules' => [$rule]]]);

    return (new Validator($registry))->validate($schema, ['code' => $answer])->errors;
}

it('matches against the whole expression when it has a comma in it', function (string $answer, array $errors) {
    expect(patternErrors('pattern:^\d{2,4}$', $answer))->toBe($errors);
})->with([
    'the fewest digits allowed' => ['12', []],
    'the most digits allowed' => ['1234', []],
    'one digit too few' => ['1', ['code' => 'pattern']],
    'one digit too many' => ['12345', ['code' => 'pattern']],
]);

it('keeps a colon that is part of the expression', function () {
    expect(patternErrors('pattern:^\d{2}:\d{2}$', '09:30'))->toBe([])
        ->and(patternErrors('pattern:^\d{2}:\d{2}$', '0930'))->toBe(['code' => 'pattern']);
});

it('still reads a list of file types as a list', function () {
    expect((new RuleRegistry())->parse('mime:application/pdf,image/png')['params'])
        ->toBe(['application/pdf', 'image/png']);
});
