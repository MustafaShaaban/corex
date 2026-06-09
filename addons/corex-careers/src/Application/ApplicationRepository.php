<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Application;

defined('ABSPATH') || exit;

use Corex\Repositories\TableRepository;

/**
 * Persists applications in the `corex_applications` custom table.
 */
final class ApplicationRepository extends TableRepository
{
    protected function table(): string
    {
        return 'applications';
    }

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return ['job_id' => 'int', 'cv_attachment' => 'int'];
    }
}
