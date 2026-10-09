<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * What a swap is about to do and how far it has got, written before it is done
 * (spec 107, plan D6; FR-022).
 *
 * `previous/journal.json`: each rename of the swap, in order, with where the folder is and where
 * it goes. A rename is marked `started` before it is made and `done` after. A request that dies
 * between two renames leaves this file saying exactly which were made, and one that dies during
 * a rename leaves one `started`, which the folders themselves then answer.
 *
 * It is kept with the folders that were replaced, because going back is this list read
 * backwards: by the Releases screen, and by the recovery file that runs when CoreX cannot.
 */
final class ReleaseJournal
{
    public const OPEN    = 'open';
    public const SWAPPED = 'swapped';
    public const UNDONE  = 'undone';

    /** A swap that could be neither finished nor wholly undone: the journal says which folders are where. */
    public const STUCK = 'stuck';

    public const PENDING = 'pending';
    public const STARTED = 'started';
    public const DONE    = 'done';

    private const FILE = 'journal.json';

    public function __construct(private readonly ReleaseStore $store)
    {
    }

    /**
     * Write down a swap before any of it is made.
     *
     * @param list<array{from:string,to:string}> $moves Each rename, in the order it is made.
     */
    public function begin(array $moves): void
    {
        $this->write([
            'status' => self::OPEN,
            'moves'  => array_map(static fn (array $move): array => [...$move, 'state' => self::PENDING], $moves),
        ]);
    }

    /** Where the last swap stands; null when there has been none. */
    public function status(): ?string
    {
        $status = $this->read()['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    /**
     * @return list<array{from:string,to:string,state:string}>
     */
    public function moves(): array
    {
        return array_values((array) ($this->read()['moves'] ?? []));
    }

    public function mark(int $move, string $state): void
    {
        $journal                           = $this->read();
        $journal['moves'][$move]['state'] = $state;
        $this->write($journal);
    }

    /**
     * @param string $refused The folder that would not move, when that is why the swap ended.
     */
    public function close(string $status, string $refused = ''): void
    {
        $this->write([...$this->read(), 'status' => $status, 'refused' => $refused]);
    }

    /** The folder that would not move, when the last swap ended for that reason. */
    public function refused(): string
    {
        return (string) ($this->read()['refused'] ?? '');
    }

    /** Where the journal is, for the recovery file that reads it when CoreX cannot. */
    public function file(): string
    {
        return $this->store->fileIn('previous', self::FILE);
    }

    /**
     * @return array<string,mixed>
     */
    private function read(): array
    {
        $file = $this->file();
        $held = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($held) ? $held : [];
    }

    /**
     * Written beside the file and moved over it, so a request that dies while writing leaves the
     * journal as it was before, not half of the next one.
     *
     * @param array<string,mixed> $journal
     */
    private function write(array $journal): void
    {
        $file    = $this->file();
        $written = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        // Direct writes, as the store's own: nothing here can stop to ask for credentials.
        file_put_contents($written, (string) wp_json_encode($journal)); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        rename($written, $file); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
    }
}
