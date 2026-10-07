<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use IteratorAggregate;
use RuntimeException;
use Traversable;

/**
 * The submissions an export has gathered so far, one per line, on disk.
 *
 * An export is gathered a batch at a time, and its columns are not known until the last batch: a
 * late submission may answer a question no earlier one did. So the batches are kept here and the
 * file is written once, at the end, from all of them. Nothing but the current batch is in memory.
 */
final readonly class SubmissionExportSpool
{
    public function __construct(private string $path)
    {
    }

    /**
     * @param list<array<string,mixed>> $records
     */
    public function append(array $records): void
    {
        $lines = '';
        foreach ($records as $record) {
            $lines .= wp_json_encode($record) . "\n";
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- appending to a private working file, batch after batch.
        if (file_put_contents($this->path, $lines, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('CoreX could not write the export working file.');
        }
    }

    /**
     * The forms the gathered submissions belong to, in the order first met.
     *
     * @return array<string,string> Form slug => the name the submission recorded for it.
     */
    public function forms(): array
    {
        $forms = [];
        foreach ($this->records() as $record) {
            $slug = (string) ($record['form'] ?? '');
            $forms[$slug] ??= (string) (($record['flow'] ?? '') ?: $slug);
        }

        return $forms;
    }

    /**
     * One form's submissions. Can be read more than once; each read goes back to the disk.
     *
     * @return iterable<array<string,mixed>>
     */
    public function of(string $form): iterable
    {
        return new class($this, $form) implements IteratorAggregate {
            public function __construct(private readonly SubmissionExportSpool $spool, private readonly string $form)
            {
            }

            public function getIterator(): Traversable
            {
                foreach ($this->spool->records() as $record) {
                    if ((string) ($record['form'] ?? '') === $this->form) {
                        yield $record;
                    }
                }
            }
        };
    }

    /**
     * @return iterable<array<string,mixed>>
     */
    public function records(): iterable
    {
        if (! is_file($this->path)) {
            return;
        }

        $stream = fopen($this->path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('CoreX could not read the export working file.');
        }

        try {
            while (($line = fgets($stream)) !== false) {
                $record = json_decode($line, true);
                if (is_array($record)) {
                    yield $record;
                }
            }
        } finally {
            fclose($stream);
        }
    }

    public function discard(): void
    {
        if (is_file($this->path)) {
            wp_delete_file($this->path);
        }
    }
}
