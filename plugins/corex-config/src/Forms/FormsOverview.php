<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Forms;

defined('ABSPATH') || exit;

/**
 * Pure view model for the Forms & Flows admin screen (spec 063, Phase 2). CoreX forms are
 * code-defined (a `Corex\Forms\Form` registers a slug + field schema + submission listeners), so this
 * screen is a truthful, read-only inventory of the REAL registered forms and their fields — not a
 * visual form builder (that stays a future capability, honestly labelled). It never invents a form or
 * a field; the corex-config boundary passes in the already-resolved registered forms, and this model
 * only shapes and counts them, including the honest empty state. WordPress-free, so it is unit-testable.
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
        $shaped     = [];

        foreach ($forms as $form) {
            $count       = count($form['fields']);
            $fieldTotal += $count;
            $shaped[]    = [
                'slug'       => $form['slug'],
                'label'      => $form['label'],
                'fieldCount' => $count,
                'fields'     => $form['fields'],
            ];
        }

        return [
            'count'      => count($shaped),
            'fieldTotal' => $fieldTotal,
            'forms'      => $shaped,
            'isEmpty'    => $shaped === [],
        ];
    }
}
