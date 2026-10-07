<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Export\ExportSpool;
use IteratorAggregate;
use Traversable;

/**
 * The submissions an export has gathered so far, one per line, on disk.
 *
 * An export is gathered a batch at a time, and its columns are not known until the last batch: a
 * late submission may answer a question no earlier one did. So the batches are kept in a working
 * file and the export is written once, at the end, from all of them. This is that working file
 * read as submissions: which forms it holds, and one form's at a time.
 */
final readonly class SubmissionExportSpool
{
    private ExportSpool $working;

    public function __construct(string $path)
    {
        $this->working = new ExportSpool($path);
    }

    /**
     * @param list<array<string,mixed>> $records
     */
    public function append(array $records): void
    {
        $this->working->append($records);
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
        return $this->working->records();
    }

    public function discard(): void
    {
        $this->working->discard();
    }
}
