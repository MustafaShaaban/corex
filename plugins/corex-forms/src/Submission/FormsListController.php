<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use WP_REST_Response;

/**
 * The read-only, capability-gated source the form block's selector reads — `GET
 * corex/v1/forms` returns `[{slug,label}]` for the registered forms, so a builder picks a
 * form from a list instead of typing its slug. Exposes only slug + label: never schemas,
 * submissions, or secrets (spec 029 FR-005/006).
 */
final class FormsListController
{
    public function __construct(private readonly FormRegistry $registry)
    {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/forms', [
            'methods'             => 'GET',
            'callback'            => [$this, 'list'],
            'permission_callback' => [$this, 'permission'],
        ]);
    }

    /**
     * Only users who can edit posts may enumerate forms in the editor.
     */
    public function permission(): bool
    {
        return current_user_can('edit_posts');
    }

    /**
     * @return list<array{slug:string,label:string}>
     */
    public function options(): array
    {
        return array_map(
            static fn (Form $form): array => ['slug' => $form->slug, 'label' => $form->label()],
            $this->registry->all(),
        );
    }

    public function list(): WP_REST_Response
    {
        return new WP_REST_Response($this->options());
    }
}
