<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Says which of a submission's answers is an uploaded file, and which file (spec 081, FR-007).
 *
 * The answer to a `file` question is stored as the id of an attachment. Whatever printed a
 * submission printed that id: the inbox showed `224` under "Answers" and an export wrote `224`
 * into its cell (reported 2026-10-11). The pane, the export and the address a file is opened at
 * all ask here, so the three cannot disagree about what a file is.
 *
 * An answer is a file when the form asks its question as one. When the form no longer asks that
 * question, it is a file only if it is the id of one uploaded through a field of that name. An
 * answer the form asks as anything else is never a file: seven years of experience is not the
 * file numbered 7.
 */
final readonly class SubmissionFileAnswers
{
    /** The type of a question that takes a file. */
    private const FILE = 'file';

    public function __construct(private SubmissionFiles $files)
    {
    }

    /**
     * The file one answer names.
     *
     * @param mixed                                            $answer    What the submission stores under the key.
     * @param list<array{key:string,label:string,type:string}> $questions What the form asks now. Empty when
     *                                                                    the form is gone.
     *
     * @return array{id:int,name:string,missing:bool}|null Null when the answer is not a file.
     */
    public function file(string $field, mixed $answer, array $questions): ?array
    {
        $askedAs = array_column($questions, 'type', 'key')[$field] ?? null;
        $id      = self::reference($answer);

        if ($id === 0 || ($askedAs !== null && $askedAs !== self::FILE)) {
            return null;
        }

        $file = $this->files->uploaded($field, $id);
        if ($file !== null || $askedAs === null) {
            return $file;
        }

        // The form asked for a file and this is what was stored for it: a file was uploaded, and
        // its record is gone. Different from nobody having sent one (FR-008).
        return ['id' => $id, 'name' => '', 'missing' => true];
    }

    /**
     * A submission for somebody reading it: each file by its name, with the address to open it.
     *
     * @param array<string,mixed>                              $record
     * @param list<array{key:string,label:string,type:string}> $questions
     *
     * @return array<string,mixed> The record, its other answers and everything else untouched.
     */
    public function describedIn(array $record, array $questions): array
    {
        $submissionId = (int) ($record['id'] ?? 0);

        return $this->withFiles($record, $questions, fn (string $field, array $file): array => $file + [
            'url' => $file['missing'] ? '' : $this->files->address($submissionId, $field),
        ]);
    }

    /**
     * A submission for a document that leaves the site: each file by its name, and no address.
     * An address is for one signed-in person, and is of no use to whoever is sent the document.
     *
     * @param array<string,mixed>                              $record
     * @param list<array{key:string,label:string,type:string}> $questions
     *
     * @return array<string,mixed>
     */
    public function namedIn(array $record, array $questions): array
    {
        return $this->withFiles($record, $questions, static fn (string $field, array $file): array => $file);
    }

    /**
     * @param array<string,mixed>                                                               $record
     * @param list<array{key:string,label:string,type:string}>                                  $questions
     * @param callable(string, array{id:int,name:string,missing:bool}): array<string,mixed>     $shown
     *
     * @return array<string,mixed>
     */
    private function withFiles(array $record, array $questions, callable $shown): array
    {
        if (! is_array($record['values'] ?? null)) {
            return $record;
        }

        foreach ($record['values'] as $field => $answer) {
            $file = $this->file((string) $field, $answer, $questions);
            if ($file !== null) {
                $record['values'][$field] = $shown((string) $field, $file);
            }
        }

        return $record;
    }

    /**
     * The attachment id an answer holds: a whole number above zero, as a number or as post meta
     * gives it back. Anything else is not a reference to a file.
     */
    private static function reference(mixed $answer): int
    {
        if (is_int($answer)) {
            return max(0, $answer);
        }

        return is_string($answer) && ctype_digit($answer) ? (int) $answer : 0;
    }
}
