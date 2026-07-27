<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Catalog;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * One form in the catalog, whatever kind of form it is.
 *
 * `submissionCount` is nullable on purpose: null means "nobody could tell us", which is a different
 * fact from zero and must not be flattened into one. Screens read {@see hasSubmissionCount()}
 * rather than testing the integer, so an unmeasured form never renders as an empty one.
 */
final class FormCatalogEntry
{
    /**
     * @param list<array{key:string,label:string,type:string,rules:list<string>}> $fields
     */
    private function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly ?int $flowId,
        public readonly string $source,
        public readonly array $fields,
        public readonly ?int $declaredFieldCount,
        public readonly bool $active,
        public readonly bool $editableInBuilder,
        public readonly ?int $submissionCount,
        public readonly string $capability,
    ) {
        if ($this->slug === '' || ! FormSource::isValid($this->source)) {
            throw new InvalidArgumentException('A form catalog entry needs a slug and a known source.');
        }
    }

    /**
     * @param list<array{key:string,label:string,type:string,rules:list<string>}> $fields
     */
    public static function create(
        string $slug,
        string $label,
        string $source,
        ?int $flowId = null,
        array $fields = [],
        ?int $declaredFieldCount = null,
        bool $active = true,
        bool $editableInBuilder = false,
        ?int $submissionCount = null,
        string $capability = 'corex_manage_forms',
    ): self {
        return new self(
            slug: $slug,
            label: $label !== '' ? $label : self::humanise($slug),
            flowId: $flowId,
            source: $source,
            fields: $fields,
            declaredFieldCount: $declaredFieldCount,
            active: $active,
            editableInBuilder: $editableInBuilder,
            submissionCount: $submissionCount,
            capability: $capability,
        );
    }

    public function withSubmissionCount(?int $count): self
    {
        return new self(
            slug: $this->slug,
            label: $this->label,
            flowId: $this->flowId,
            source: $this->source,
            fields: $this->fields,
            declaredFieldCount: $this->declaredFieldCount,
            active: $this->active,
            editableInBuilder: $this->editableInBuilder,
            submissionCount: $count,
            capability: $this->capability,
        );
    }

    /**
     * The number of fields, preferring real definitions over a declared total.
     *
     * A provider may know its count without being able to hand over the definitions; a code form
     * always has the definitions themselves. Counting what we hold beats trusting what we were told.
     */
    public function fieldCount(): int
    {
        return $this->fields !== [] ? count($this->fields) : max(0, (int) $this->declaredFieldCount);
    }

    public function hasSubmissionCount(): bool
    {
        return $this->submissionCount !== null;
    }

    /**
     * A one-line "field: rule, rule" digest of the form's validation, for the read-only view of a
     * form nobody can open in the builder. Empty when no field declares a rule.
     */
    public function validationSummary(): string
    {
        $parts = [];

        foreach ($this->fields as $field) {
            if ($field['rules'] !== []) {
                $parts[] = $field['key'] . ': ' . implode(', ', $field['rules']);
            }
        }

        return implode(' · ', $parts);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'slug'                => $this->slug,
            'label'               => $this->label,
            'flow_id'             => $this->flowId,
            'source'              => $this->source,
            'fields'              => $this->fields,
            'field_count'         => $this->fieldCount(),
            'active'              => $this->active,
            'editable_in_builder' => $this->editableInBuilder,
            'submission_count'    => $this->submissionCount,
            'validation_summary'  => $this->validationSummary(),
            'capability'          => $this->capability,
        ];
    }

    private static function humanise(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }
}
