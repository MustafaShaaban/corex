<?php

/**
 * Forms extension-registry contracts (spec 068: FR-032, FR-033, FR-040).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Schema\FieldTypeRegistry;
use Corex\Forms\Flow\EmailVariableRegistry;
use Corex\Forms\Flow\FlowActionRegistry;
use Corex\Forms\Success\SuccessStateRegistry;
use Corex\Forms\Validation\Rule;
use Corex\Forms\Validation\RuleRegistry;

it('registers every required built-in field type in design order', function () {
    expect((new FieldTypeRegistry())->keys())->toBe([
        'text',
        'email',
        'phone',
        'number',
        'textarea',
        'select',
        'multi-select',
        'radio',
        'checkbox',
        'date',
        'time',
        'url',
        'hidden',
        'consent',
        'rating',
        'step',
    ]);
});

it('registers a custom field type once and exposes its editor settings', function () {
    $registry = new FieldTypeRegistry();
    $registry->register('currency', 'Currency', ['options' => false, 'default_value' => true]);

    expect($registry->get('currency'))->toBe([
        'key' => 'currency',
        'label' => 'Currency',
        'settings' => ['options' => false, 'default_value' => true],
        'built_in' => false,
    ]);

    expect(fn () => $registry->register('currency', 'Duplicate'))->toThrow(InvalidArgumentException::class);
});

it('ships required validation rules and safely accepts registered custom rules', function () {
    $registry = new RuleRegistry();
    $custom = new class implements Rule {
        public function validate(mixed $value, array $params, array $allValues): ?string
        {
            return $value === 'allowed' ? null : 'registered_only';
        }
    };

    $registry->register('registered_only', $custom);

    expect($registry->keys())->toContain(
        'required',
        'email',
        'url',
        'min_length',
        'max_length',
        'numeric',
        'pattern',
        'registered_only',
    )
        ->and($registry->get('registered_only')->validate('denied', [], []))->toBe('registered_only')
        ->and($registry->get('url')->validate('not a url', [], []))->toBe('url')
        ->and($registry->get('url')->validate('https://example.com', [], []))->toBeNull()
        ->and($registry->get('min_length')->validate('abc', ['4'], []))->toBe('min')
        ->and($registry->get('max_length')->validate('abcde', ['4'], []))->toBe('max')
        ->and($registry->get('pattern')->validate('CX-42', ['^CX-[0-9]+$'], []))->toBeNull()
        ->and($registry->get('pattern')->validate('wrong', ['^CX-[0-9]+$'], []))->toBe('pattern')
        ->and($registry->get('pattern')->validate('value', ['['], []))->toBe('pattern')
        ->and($registry->get('pattern')->validate('value', [str_repeat('a', 513)], []))->toBe('pattern')
        ->and(fn () => $registry->register('required', $custom))->toThrow(InvalidArgumentException::class);
});

it('executes registered flow actions and resolves only scalar email variables', function () {
    $actions = new FlowActionRegistry();
    $variables = new EmailVariableRegistry();
    $actions->register('crm_sync', 'CRM sync', static fn (array $context): array => [
        'contact_id' => $context['submission_id'],
    ]);
    $variables->register('submission.reference', 'Submission reference', static fn (array $context): string => (string) $context['submission_id']);

    expect($actions->execute('crm_sync', ['submission_id' => 42]))->toBe(['contact_id' => 42])
        ->and($variables->resolve('submission.reference', ['submission_id' => 42]))->toBe('42')
        ->and($actions->definitions()[0]['key'])->toBe('crm_sync')
        ->and($variables->definitions()[0]['key'])->toBe('submission.reference');
});

it('provides built-in success states and accepts a registered custom state', function () {
    $registry = new SuccessStateRegistry();
    $registry->register('download', 'Download', static fn (array $configuration): array => [
        'type' => 'download',
        'attachment_id' => (int) $configuration['attachment_id'],
    ]);

    expect($registry->keys())->toBe(['inline', 'page', 'url', 'download'])
        ->and($registry->normalize('download', ['attachment_id' => '19']))->toBe([
            'type' => 'download',
            'attachment_id' => 19,
        ])
        ->and(fn () => $registry->register('inline', 'Duplicate', static fn (): array => []))
        ->toThrow(InvalidArgumentException::class);
});
