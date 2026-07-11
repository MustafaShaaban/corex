<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Forms;

defined('ABSPATH') || exit;

/**
 * Compatibility projection for code-defined registered forms. Persisted Flow builder data uses its
 * own REST projections; this keeps the legacy inventory truthful without fabricating field or rule state.
 */
final class FormsOverview
{
    /**
     * @param list<array{slug:string,label:string,fields:list<array{name:string,type:string,label:string,required:bool,rules:list<string>}>}> $forms
     *
     * @return array{
     *   count:int,
     *   fieldTotal:int,
     *   forms:list<array{slug:string,label:string,fieldCount:int,fields:list<array{name:string,type:string,label:string,required:bool,rules:list<string>}>}>,
     *   isEmpty:bool
     * }
     */
    public function summary(array $forms): array
    {
        $fieldTotal = 0;
        $shaped = [];

        foreach ($forms as $form) {
            $fieldCount = count($form['fields']);
            $fieldTotal += $fieldCount;
            $shaped[] = [
                'slug' => $form['slug'],
                'label' => $form['label'],
                'fieldCount' => $fieldCount,
                'fields' => $form['fields'],
            ];
        }

        return [
            'count' => count($shaped),
            'fieldTotal' => $fieldTotal,
            'forms' => $shaped,
            'isEmpty' => $shaped === [],
        ];
    }
}
