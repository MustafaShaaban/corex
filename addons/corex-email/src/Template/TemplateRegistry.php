<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Template;

defined('ABSPATH') || exit;

/**
 * Holds the registered templates by name. An unknown name resolves to null so the
 * send path degrades non-fatally (spec FR-002, FR-011).
 */
final class TemplateRegistry
{
    /**
     * @var array<string,EmailTemplate>
     */
    private array $templates = [];

    public function register(EmailTemplate $template): void
    {
        $this->templates[$template->name()] = $template;
    }

    public function find(string $name): ?EmailTemplate
    {
        return $this->templates[$name] ?? null;
    }

    /**
     * The names of every registered template, in registration order. Lets the admin surface a
     * truthful inventory of the real templates without instantiating a send context (spec 063).
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->templates);
    }
}
