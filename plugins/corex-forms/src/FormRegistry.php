<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms;

defined('ABSPATH') || exit;

/**
 * Holds the registered forms, keyed by slug. An unknown slug resolves to null so
 * the submit endpoint and block degrade non-fatally (spec FR-018).
 */
final class FormRegistry
{
    /**
     * @var array<string,Form>
     */
    private array $forms = [];

    public function register(Form $form): void
    {
        $this->forms[$form->slug] = $form;
    }

    public function find(string $slug): ?Form
    {
        return $this->forms[$slug] ?? null;
    }

    /**
     * @return list<Form>
     */
    public function all(): array
    {
        return array_values($this->forms);
    }
}
