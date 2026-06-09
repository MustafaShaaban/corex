<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Events\EventDispatcher;
use Corex\Forms\FormRegistry;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Validation\Validator;
use Corex\Http\Middleware\Response;

/**
 * Orchestrates a submission once the security middleware has run: honeypot check →
 * resolve the form's schema → validate → on success, dispatch the FormSubmittedEvent.
 * Pure of WordPress; every rejection short-circuits before any side effect (FR-008,
 * FR-010, FR-011, SC-006). The side effects themselves live in the listeners.
 */
final class FormSubmissionService
{
    public function __construct(
        private readonly FormRegistry $forms,
        private readonly SchemaResolver $resolver,
        private readonly Validator $validator,
        private readonly EventDispatcher $events,
    ) {
    }

    /**
     * The resolved field schema for a form, or [] for an unknown slug. Used by the
     * REST boundary to build a form-shaped sanitizer without duplicating resolution.
     *
     * @return array<string,\Corex\Forms\Schema\FieldSchema>
     */
    public function schemaFor(string $slug): array
    {
        $form = $this->forms->find($slug);

        return $form === null ? [] : $this->resolver->resolve($form->fields());
    }

    /**
     * @param array<string,mixed> $input sanitized values (the honeypot key included)
     */
    public function handle(string $slug, array $input, string $honeypotKey = 'corex_hp'): Response
    {
        $form = $this->forms->find($slug);

        if ($form === null) {
            return Response::reject('Unknown form.', 404);
        }

        // A filled honeypot means a bot: reject silently, no dispatch, no side effect.
        if (isset($input[$honeypotKey]) && trim((string) $input[$honeypotKey]) !== '') {
            return Response::reject('Submission rejected.', 422);
        }

        $result = $this->validator->validate($this->resolver->resolve($form->fields()), $input);

        if (! $result->isValid()) {
            return Response::reject('Validation failed.', 422, $result->errors);
        }

        $this->events->dispatch(new FormSubmittedEvent($slug, $result->values));

        return Response::ok($result->values);
    }
}
