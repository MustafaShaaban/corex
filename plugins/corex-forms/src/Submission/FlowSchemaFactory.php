<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Schema\FieldSchema;
use Corex\Forms\Schema\SchemaResolver;

/**
 * Adapts a versioned builder schema to the existing validation schema.
 */
final readonly class FlowSchemaFactory
{
    public function __construct(private SchemaResolver $resolver)
    {
    }

    /** @return array<string,FieldSchema> */
    public function make(FlowConfiguration $configuration): array
    {
        $definitions = [];
        foreach ($configuration->schema as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $rules = array_values(array_map('strval', (array) ($configuration->validation[$key] ?? [])));
            if (($field['required'] ?? false) && ! in_array('required', $rules, true)) {
                array_unshift($rules, 'required');
            }
            $definitions[$key] = [
                'type' => (string) ($field['type'] ?? 'text'),
                'label' => (string) ($field['label'] ?? $key),
                'rules' => $rules,
                'options' => $this->options((array) ($field['options'] ?? [])),
                'width' => (string) ($field['width'] ?? 'full'),
                'placeholder' => (string) ($field['placeholder'] ?? ''),
                'help_text' => (string) ($field['help_text'] ?? ''),
                'default_value' => $field['default_value'] ?? null,
                'visibility' => (string) ($field['visibility'] ?? 'visible'),
                'step_key' => (string) ($field['step_key'] ?? ''),
                'personal_data_class' => (string) ($field['personal_data_class'] ?? 'none'),
                'extension_config' => (array) ($field['extension_config'] ?? []),
            ];
        }

        return $this->resolver->resolve($definitions);
    }

    /**
     * @param array<int|string,mixed> $options
     * @return array<string,string>
     */
    private function options(array $options): array
    {
        $mapped = [];
        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $value = (string) ($option['value'] ?? '');
                if ($value !== '') {
                    $mapped[$value] = (string) ($option['label'] ?? $value);
                }
                continue;
            }
            $mapped[(string) $key] = (string) $option;
        }

        return $mapped;
    }
}
