<?php

/**
 * Shared fixtures for the data-layer unit tests. Required directly (several
 * classes in one file) and ignored by Pest as a non-test file.
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

namespace Corex\Tests\Fixtures\Data;

use Corex\Models\Model;

final class Company extends Model
{
    public static function postType(): string
    {
        return 'company';
    }

    public static function fields(): array
    {
        return [];
    }
}

final class Job extends Model
{
    public static function postType(): string
    {
        return 'job';
    }

    public static function fields(): array
    {
        return ['salary' => 'job_salary'];
    }

    public static function casts(): array
    {
        return [
            'salary'  => 'int',
            'active'  => 'bool',
            'created' => \DateTimeImmutable::class,
        ];
    }

    public static function relations(): array
    {
        return ['company' => ['type' => 'belongsTo', 'model' => Company::class, 'foreignKey' => 'company_id']];
    }
}
