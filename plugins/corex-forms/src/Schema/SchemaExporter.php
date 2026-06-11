<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Schema;

defined('ABSPATH') || exit;

/**
 * Serializes a resolved schema (the PHP source of truth) into the JSON-able shape the
 * client validator consumes — so client and server validate against ONE definition,
 * never two hand-kept-in-sync copies. Pure: no WordPress, no encoding (the caller
 * wp_json_encode()s + escapes it at the output boundary).
 */
final class SchemaExporter
{
    /**
     * @param array<string,FieldSchema> $schema
     *
     * @return list<array{name:string,type:string,label:string,required:bool,rules:list<array{rule:string,params:list<string>}>}>
     */
    public function toArray(array $schema): array
    {
        $fields = [];

        foreach ($schema as $field) {
            $fields[] = [
                'name'     => $field->name,
                'type'     => $field->type,
                'label'    => $field->label,
                'required' => $field->required,
                'rules'    => $field->rules,
            ];
        }

        return $fields;
    }
}
