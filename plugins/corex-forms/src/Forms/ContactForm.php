<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Forms;

defined('ABSPATH') || exit;

use Corex\Forms\Form;

/**
 * The example form proving the lifecycle end-to-end: name, email, and message,
 * each validated, stored, and emailed on a valid submission. Labels are
 * translation-ready (resolved at call time, when WordPress i18n is available).
 */
final class ContactForm extends Form
{
    public string $slug = 'contact';

    /**
     * @return array<string,array{type:string,rules:list<string>,label:string}>
     */
    public function fields(): array
    {
        return [
            'name'    => ['type' => 'text', 'rules' => ['required', 'max:120'], 'label' => __('Name', 'corex')],
            'email'   => ['type' => 'email', 'rules' => ['required', 'email'], 'label' => __('Email', 'corex')],
            'message' => ['type' => 'textarea', 'rules' => ['required', 'max:2000'], 'label' => __('Message', 'corex')],
        ];
    }
}
