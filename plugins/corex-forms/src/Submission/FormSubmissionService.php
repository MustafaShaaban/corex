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
use Corex\Security\Upload\AttachmentStorage;

/**
 * Orchestrates a submission once the security middleware has run: honeypot check →
 * resolve the form's schema → validate → on success, dispatch the FormSubmittedEvent.
 * Pure of WordPress; every rejection short-circuits before any side effect (FR-008,
 * FR-010, FR-011, SC-006). The side effects themselves live in the listeners.
 */
final class FormSubmissionService
{
    /** The hidden anti-bot field name, shared by the controller and the block renderer. */
    public const HONEYPOT_KEY = 'corex_hp';

    public function __construct(
        private readonly FormRegistry $forms,
        private readonly SchemaResolver $resolver,
        private readonly Validator $validator,
        private readonly EventDispatcher $events,
        /**
         * Optional so every existing construction site is untouched. A form with no file field
         * never reaches it; a form with one and no store configured refuses the submission rather
         * than accepting it and losing the file, which is the failure mode this whole spec exists
         * to remove.
         */
        private readonly ?AttachmentStorage $attachments = null,
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
     * @param array<string,mixed>                                                                   $input sanitized values
     *                                                                                                     (the honeypot key
     *                                                                                                     included)
     * @param array<string,array{name?:string,type?:string,size?:int,tmp_name?:string,error?:int}>  $files uploaded
     *                                                                                                     descriptors,
     *                                                                                                     keyed by field
     */
    public function handle(
        string $slug,
        array $input,
        string $honeypotKey = self::HONEYPOT_KEY,
        array $files = [],
    ): Response {
        $form = $this->forms->find($slug);

        if ($form === null) {
            return Response::reject(__('Unknown form.', 'corex'), 404);
        }

        // A filled honeypot means a bot: reject silently, no dispatch, no side effect.
        if (isset($input[$honeypotKey]) && trim((string) $input[$honeypotKey]) !== '') {
            return Response::reject(__('Submission rejected.', 'corex'), 422);
        }

        $schema = $this->resolver->resolve($form->fields());

        // Descriptors are validated in place, so `mime` and `max_size` see the real file. Nothing
        // is stored yet: FR-005 says a refused submission leaves no file behind, and the simplest
        // way to guarantee that is to have written nothing when the refusal happens.
        $result = $this->validator->validate($schema, $this->withDescriptors($schema, $input, $files));

        if (! $result->isValid()) {
            return Response::reject(__('Validation failed.', 'corex'), 422, $result->errors);
        }

        $values = $this->storeUploads($schema, $result->values);
        if ($values === null) {
            return Response::reject(__('The file could not be stored.', 'corex'), 422);
        }

        $this->events->dispatch(new FormSubmittedEvent($slug, $values));

        return Response::ok($values);
    }

    /**
     * Put each uploaded descriptor where its field's value would be, so the rules can see it.
     *
     * @param array<string,FieldSchema>                                                            $schema
     * @param array<string,mixed>                                                                  $input
     * @param array<string,array{name?:string,type?:string,size?:int,tmp_name?:string,error?:int}> $files
     *
     * @return array<string,mixed>
     */
    private function withDescriptors(array $schema, array $input, array $files): array
    {
        foreach ($schema as $name => $field) {
            if ($field->type !== 'file') {
                continue;
            }

            // Absent stays absent rather than becoming an empty descriptor: `required` must be able
            // to tell "no file was sent" from "a file was sent and it failed".
            if (isset($files[$name])) {
                $input[$name] = $files[$name];
            }
        }

        return $input;
    }

    /**
     * Exchange each validated descriptor for the attachment id it stored as.
     *
     * @param array<string,FieldSchema> $schema
     * @param array<string,mixed>       $values
     *
     * @return array<string,mixed>|null null when a file could not be stored, with anything already
     *                                  stored for this submission removed again
     */
    private function storeUploads(array $schema, array $values): ?array
    {
        $storedIds = [];

        foreach ($schema as $name => $field) {
            if ($field->type !== 'file' || ! is_array($values[$name] ?? null)) {
                continue;
            }

            if ($this->attachments === null) {
                return null;
            }

            $result = $this->attachments->store($values[$name], 'form-' . $name);

            if (! $result->stored) {
                // A form with two file fields whose second upload fails must not leave the first
                // one on disk with nothing referring to it — that is personal data nobody can find
                // to delete (FR-005).
                foreach ($storedIds as $id) {
                    $this->attachments->forget($id);
                }

                return null;
            }

            $storedIds[]   = $result->attachmentId;
            $values[$name] = $result->attachmentId;
        }

        return $values;
    }
}
