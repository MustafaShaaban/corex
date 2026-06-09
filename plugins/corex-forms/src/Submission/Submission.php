<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Models\Model;

/**
 * One stored form submission. Its dynamic field values are written as `corex_field_*`
 * meta by the repository (the field names vary per form), so the declared field map
 * is empty here.
 */
final class Submission extends Model
{
    public static function postType(): string
    {
        return 'corex_submission';
    }

    /**
     * @return array<string,string>
     */
    public static function fields(): array
    {
        return [];
    }
}
