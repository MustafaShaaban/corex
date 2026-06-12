<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms;

defined('ABSPATH') || exit;

use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Listeners\StoreSubmissionListener;

/**
 * A code-defined form: its slug, its field definitions, and the listeners that
 * handle its submissions. The single source feeding the schema resolver, the
 * submit endpoint, and the block. A concrete form sets $slug and $fields.
 */
abstract class Form
{
    public string $slug = '';

    /**
     * @var array<string,array{type?:string,rules?:list<string>,label?:string}>
     */
    protected array $fields = [];

    /**
     * @return array<string,array{type?:string,rules?:list<string>,label?:string}>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * A human label for the form, shown in the block's form selector. Defaults to a
     * humanized slug; a concrete form may override for a nicer name.
     */
    public function label(): string
    {
        return $this->slug === '' ? '' : ucwords(str_replace(['-', '_'], ' ', $this->slug));
    }

    /**
     * Listener service ids for this form's submissions. The default set stores the
     * submission and emails a notification; concrete forms may override.
     *
     * @return list<class-string>
     */
    public function listeners(): array
    {
        return [StoreSubmissionListener::class, SendEmailListener::class];
    }
}
