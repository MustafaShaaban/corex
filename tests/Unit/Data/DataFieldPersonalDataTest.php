<?php

/**
 * Which fields are asked about and guarded as personal data (spec 109, FR-016, FR-017).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Data\DataField;

it('holds personal data in every class but none, a class nobody declared among them', function (string $class, bool $holds) {
    $field = new DataField('email', 'Email', DataField::TYPE_TEXT, false, true, true, [], false, $class, [], []);

    expect($field->holdsPersonalData())->toBe($holds);
})->with([
    'declared as holding none' => [DataField::PERSONAL_NONE, false],
    'declared as contact details' => [DataField::PERSONAL_CONTACT, true],
    // The safe reading of silence: a column whose model said nothing is asked about and
    // guarded like one that holds a name.
    'declared as nothing at all' => [DataField::PERSONAL_UNDECLARED, true],
]);
