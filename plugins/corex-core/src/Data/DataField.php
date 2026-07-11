<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Data;

defined('ABSPATH') || exit;

use InvalidArgumentException;

final class DataField
{
    public const TYPE_ID       = 'id';
    public const TYPE_TEXT     = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_EMAIL    = 'email';
    public const TYPE_TEL      = 'tel';
    public const TYPE_URL      = 'url';
    public const TYPE_INTEGER  = 'integer';
    public const TYPE_DECIMAL  = 'decimal';
    public const TYPE_BOOLEAN  = 'boolean';
    public const TYPE_DATE     = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_SELECT   = 'select';
    public const TYPE_JSON     = 'json';
    public const TYPE_FORM     = 'form';

    public const PERSONAL_NONE       = 'none';
    public const PERSONAL_CONTACT    = 'contact';
    public const PERSONAL_IDENTITY   = 'identity';
    public const PERSONAL_CONTENT    = 'content';
    public const PERSONAL_SECURITY   = 'security';
    public const PERSONAL_BEHAVIORAL = 'behavioral';
    public const PERSONAL_SENSITIVE  = 'sensitive';

    private const TYPES = [
        self::TYPE_ID,
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_EMAIL,
        self::TYPE_TEL,
        self::TYPE_URL,
        self::TYPE_INTEGER,
        self::TYPE_DECIMAL,
        self::TYPE_BOOLEAN,
        self::TYPE_DATE,
        self::TYPE_DATETIME,
        self::TYPE_SELECT,
        self::TYPE_JSON,
        self::TYPE_FORM,
    ];

    private const PERSONAL_CLASSES = [
        self::PERSONAL_NONE,
        self::PERSONAL_CONTACT,
        self::PERSONAL_IDENTITY,
        self::PERSONAL_CONTENT,
        self::PERSONAL_SECURITY,
        self::PERSONAL_BEHAVIORAL,
        self::PERSONAL_SENSITIVE,
    ];

    private const OPERATORS = [
        'equals',
        'not_equals',
        'contains',
        'not_contains',
        'starts_with',
        'ends_with',
        'gt',
        'gte',
        'lt',
        'lte',
        'in',
        'not_in',
        'is_empty',
        'not_empty',
        'between',
    ];

    /**
     * @param list<string>        $filterOperators
     * @param array<string,mixed> $validation
     * @param list<string>        $importAliases
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly bool $required,
        public readonly bool $nullable,
        public readonly bool $readOnly,
        public readonly array $filterOperators,
        public readonly bool $sortable,
        public readonly string $personalDataClass,
        public readonly array $validation,
        public readonly array $importAliases,
    ) {
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $this->key) !== 1 || $this->label === '') {
            throw new InvalidArgumentException('Data field key or label is invalid.');
        }

        if (! in_array($this->type, self::TYPES, true)
            || ! in_array($this->personalDataClass, self::PERSONAL_CLASSES, true)) {
            throw new InvalidArgumentException('Data field type or personal-data class is invalid.');
        }

        if ($this->required && $this->nullable) {
            throw new InvalidArgumentException('A required data field cannot also be nullable.');
        }

        foreach ($this->filterOperators as $operator) {
            if (! in_array($operator, self::OPERATORS, true)) {
                throw new InvalidArgumentException('Data field filter operator is invalid.');
            }
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'key'                 => $this->key,
            'label'               => $this->label,
            'type'                => $this->type,
            'required'            => $this->required,
            'nullable'            => $this->nullable,
            'read_only'           => $this->readOnly,
            'filter_operators'    => $this->filterOperators,
            'sortable'            => $this->sortable,
            'personal_data_class' => $this->personalDataClass,
            'validation'          => $this->validation,
            'import_aliases'      => $this->importAliases,
        ];
    }
}
