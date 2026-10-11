<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\FormRegistry;

/**
 * The names of the files a form was sent with.
 *
 * A submitted file reaches the listeners as the number it was stored under. A person calls it by
 * its name, and an email that printed the number said "Cv: 224" (spec 081, US3).
 */
final class UploadedFileNames
{
    private const FILE = 'file';

    public function __construct(private readonly FormRegistry $forms)
    {
    }

    /**
     * @param array<string,mixed> $values The submitted values, a stored file as its number.
     *
     * @return array<string,string> The name of each file, by its field. A file that cannot be
     *                              found is left out.
     */
    public function in(string $formSlug, array $values): array
    {
        $names = [];

        foreach ($this->forms->find($formSlug)?->fields() ?? [] as $field => $definition) {
            $stored = $values[$field] ?? null;
            if (($definition['type'] ?? '') !== self::FILE || ! is_int($stored) || $stored < 1) {
                continue;
            }

            $path = get_attached_file($stored);
            if (is_string($path) && $path !== '') {
                $names[(string) $field] = basename($path);
            }
        }

        return $names;
    }
}
