<?php

/**
 * Which classes of a field count as personal data (spec 109, FR-016, FR-017).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Data\DataField;

function fieldOfClass(string $class): DataField
{
    return new DataField('email', 'Email', DataField::TYPE_TEXT, false, true, true, [], false, $class, [], []);
}

it('holds no personal data only when its class is none', function () {
    expect(fieldOfClass(DataField::PERSONAL_NONE)->holdsPersonalData())->toBeFalse();
});

it('holds personal data in every other class', function (string $class) {
    expect(fieldOfClass($class)->holdsPersonalData())->toBeTrue();
})->with([
    DataField::PERSONAL_CONTACT,
    DataField::PERSONAL_IDENTITY,
    DataField::PERSONAL_CONTENT,
    DataField::PERSONAL_SECURITY,
    DataField::PERSONAL_BEHAVIORAL,
    DataField::PERSONAL_SENSITIVE,
]);

it('treats a field nobody classed as personal data', function () {
    // The safe reading of silence: a column whose model said nothing is asked about and
    // guarded like one that holds a name.
    $field = fieldOfClass(DataField::PERSONAL_UNDECLARED);

    expect($field->holdsPersonalData())->toBeTrue()
        ->and($field->toArray()['personal_data_class'])->toBe('undeclared');
});
