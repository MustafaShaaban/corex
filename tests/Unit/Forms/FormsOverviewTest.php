<?php

/**
 * Unit tests for the pure Forms & Flows admin view model (spec 063, Phase 2). No WordPress.
 * Contract: shape and count the REAL registered forms; never invent a form or field.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Config\Forms\FormsOverview;

/**
 * @return array{slug:string,label:string,fields:list<array{name:string,type:string,label:string,required:bool,rules:list<string>}>}
 */
function formFixture(string $slug, array $fields): array
{
    return ['slug' => $slug, 'label' => ucfirst($slug), 'fields' => $fields];
}

it('counts forms and their fields from the real registered set', function () {
    $forms = [
        formFixture('contact', [
            ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'rules' => ['required', 'max:120']],
            ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'rules' => ['required', 'email']],
        ]),
        formFixture('newsletter', [
            ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'rules' => ['required', 'email']],
        ]),
    ];

    $summary = (new FormsOverview())->summary($forms);

    expect($summary['count'])->toBe(2)
        ->and($summary['fieldTotal'])->toBe(3)
        ->and($summary['isEmpty'])->toBeFalse()
        ->and($summary['forms'][0]['fieldCount'])->toBe(2)
        ->and($summary['forms'][1]['fieldCount'])->toBe(1);
});

it('reports an honest empty state when no forms are registered', function () {
    $summary = (new FormsOverview())->summary([]);

    expect($summary['count'])->toBe(0)
        ->and($summary['fieldTotal'])->toBe(0)
        ->and($summary['isEmpty'])->toBeTrue()
        ->and($summary['forms'])->toBe([]);
});

it('preserves each field definition verbatim — never fabricating rules or requirement', function () {
    $forms = [formFixture('contact', [
        ['name' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => false, 'rules' => ['max:2000']],
    ])];

    $field = (new FormsOverview())->summary($forms)['forms'][0]['fields'][0];

    expect($field['name'])->toBe('message')
        ->and($field['type'])->toBe('textarea')
        ->and($field['required'])->toBeFalse()
        ->and($field['rules'])->toBe(['max:2000']);
});
