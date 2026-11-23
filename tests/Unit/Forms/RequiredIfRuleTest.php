<?php

/**
 * The `required_if` rule (spec 129, FR-023). No WordPress.
 *
 * Contract: a field is required only while another field holds one of the listed answers, a request
 * that leaves the field out is still asked, and a field that is not needed may stay empty.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;

/**
 * The hand-off's two contact fields, each needed by some ways of being reached.
 *
 * @param array<string, mixed> $values
 * @return array<string, string>
 */
function requiredIfChannelErrors(array $values): array
{
    $registry = new RuleRegistry();
    $schema = (new SchemaResolver($registry))->resolve([
        'channel' => ['type' => 'text', 'rules' => ['required']],
        'phone' => ['type' => 'text', 'rules' => ['required_if:channel,call,whatsapp']],
        'email' => ['type' => 'email', 'rules' => ['required_if:channel,email', 'email']],
    ]);

    return (new Validator($registry))->validate($schema, $values)->errors;
}

it('requires the field only for the answers it lists', function (array $values, array $errors) {
    expect(requiredIfChannelErrors($values))->toBe($errors);
})->with([
    'a call needs a phone' => [['channel' => 'call', 'phone' => '', 'email' => ''], ['phone' => 'required']],
    'WhatsApp needs a phone' => [['channel' => 'whatsapp', 'phone' => '  ', 'email' => 'a@example.com'], ['phone' => 'required']],
    'an email needs an email' => [['channel' => 'email', 'phone' => '+20 100 000 0000', 'email' => ''], ['email' => 'required']],
    'a call with a phone needs no email' => [['channel' => 'call', 'phone' => '+20 100 000 0000', 'email' => ''], []],
    'an email with an email needs no phone' => [['channel' => 'email', 'phone' => '', 'email' => 'a@example.com'], []],
]);

it('asks about a field the request left out, so writing the request by hand does not get past it', function () {
    expect(requiredIfChannelErrors(['channel' => 'call']))->toBe(['phone' => 'required'])
        ->and(requiredIfChannelErrors(['channel' => 'email']))->toBe(['email' => 'required']);
});

it('still runs the field’s other rules once it is given', function () {
    expect(requiredIfChannelErrors(['channel' => 'email', 'email' => 'not-an-address']))->toBe(['email' => 'email']);
});

it('asks for nothing without a field to follow or an answer to match', function (array $params) {
    expect((new \Corex\Forms\Validation\Rules\RequiredIf())->validate('', $params, ['channel' => 'call']))->toBeNull();
})->with([
    'no parameters' => [[]],
    'a field and no answers' => [['channel']],
    'a field nobody answered' => [['topic', 'sales']],
]);
