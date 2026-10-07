<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * What a form asked, in its own words and order.
 *
 * An export heads each answer with its question, and the detail pane does the same. Both ask here,
 * so neither reaches into how a form is defined.
 */
interface SubmissionQuestions
{
    /**
     * @param string $form The form's slug, as a submission records it.
     *
     * @return list<array{key:string,label:string,type:string}> Empty when the form is gone or the
     *                                                          forms plugin is not active.
     */
    public function for(string $form): array;
}
