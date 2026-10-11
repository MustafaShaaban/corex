<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\SchemaRegistry;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A copy of CoreX's tables as they were, made before a release changes them (spec 107, FR-034,
 * FR-044; plan D10).
 *
 * One copy of each table in the schema registry that exists on the site, named for the table
 * and the minute it was made: `wp_corex_activity_cxrel_202610111530`. The name is the whole
 * record, so nothing about a copy is kept in a folder a release replaces or a table it
 * migrates. One set is kept: an installation discards the copies of the one before it.
 *
 * This is not a backup of the site. What is kept as posts and options (submissions, forms,
 * settings) is not in it, and on a network it holds the tables of the site the Releases screen
 * is on. A copy is for a person to read from; nothing here puts one back.
 */
final class ReleaseTableCopies
{
    public const FAILED = 'table_copy_failed';

    /** How long a copy is kept. */
    public const DAYS = 14;

    /** Between a table's name and the minute its copy was made. */
    private const MARK = '_cxrel_';

    private const A_COPY = '/' . self::MARK . '(\d{12})$/';

    /** The longest name MySQL takes for a table. */
    private const LONGEST_NAME = 64;

    /** How long a request copies before it answers: well inside the thirty seconds a host commonly allows. */
    private const SECONDS = 8.0;

    public function __construct(
        private readonly ReleaseTableCopier $database,
        private readonly SchemaRegistry $schemas,
        private readonly SchemaMigrator $migrator,
        private readonly float $seconds = self::SECONDS,
    ) {
    }

    /**
     * Copy more of the tables, from the one given.
     *
     * At least one table is copied, however little time there is. From the first, the copies
     * an earlier installation left are discarded.
     *
     * @param int               $cursor The table to go on from: 0 to begin, then what the last request answered.
     * @param DateTimeImmutable $at     When the installation began; every copy of it carries this minute.
     *
     * @return array{cursor:int,of:int} Where the next request starts, and how many tables there are: equal when all are copied.
     *
     * @throws ReleaseRefused When a table could not be copied whole. No copy of this attempt is kept.
     */
    public function copy(int $cursor, DateTimeImmutable $at): array
    {
        if ($cursor === 0) {
            $this->discard();
        }

        $tables = $this->tables();
        $until  = microtime(true) + $this->seconds;

        while ($cursor < count($tables)) {
            $this->copyOne($tables[$cursor], $at);
            $cursor++;

            if (microtime(true) >= $until) {
                break;
            }
        }

        return ['cursor' => $cursor, 'of' => count($tables)];
    }

    /**
     * The copies there are, with when each was made and when it is removed.
     *
     * @return list<array{table:string,copy:string,made_at:string,goes_at:string}>
     */
    public function kept(): array
    {
        $kept = [];

        foreach ($this->found() as $copy => $madeAt) {
            $kept[] = [
                'table'   => $this->tableOf($copy, $madeAt),
                'copy'    => $copy,
                'made_at' => $madeAt->format(DATE_ATOM),
                'goes_at' => $this->goesAt($madeAt)->format(DATE_ATOM),
            ];
        }

        return $kept;
    }

    /** Remove every copy. */
    public function discard(): void
    {
        foreach (array_keys($this->found()) as $copy) {
            $this->database->drop($copy);
        }
    }

    /**
     * @throws ReleaseRefused
     */
    private function copyOne(string $table, DateTimeImmutable $at): void
    {
        $copy = $this->nameFor($table, $at);

        // What a request that was cut off while copying left under this name is not a copy.
        $this->database->drop($copy);

        if ($this->database->copy($table, $copy)) {
            return;
        }

        $this->discard();

        throw new ReleaseRefused(self::FAILED, sprintf(
            /* translators: %s: the name of a database table. */
            __('The table %s could not be copied before the release changes it, so nothing was installed and the site is as it was. A table too large to copy in one request is the usual reason: copy it with the host\'s database tool, then ask the host how long a request may run.', 'corex'),
            $table,
        ));
    }

    /**
     * The registered tables that exist on this site, by their full names, in a settled order.
     *
     * @return list<string>
     */
    private function tables(): array
    {
        $tables = array_map($this->migrator->fullName(...), $this->schemas->tableNames());
        sort($tables);

        return array_values(array_filter(array_unique($tables), $this->database->exists(...)));
    }

    /**
     * Every copy on the site, with the minute in its name.
     *
     * @return array<string,DateTimeImmutable>
     */
    private function found(): array
    {
        $found = [];

        // Every table of CoreX's begins as the migrator names one, and so does every copy.
        foreach ($this->database->tablesStartingWith($this->migrator->fullName('')) as $table) {
            if (preg_match(self::A_COPY, $table, $minute) !== 1) {
                continue;
            }

            $madeAt = DateTimeImmutable::createFromFormat('!YmdHi', $minute[1], new DateTimeZone('UTC'));

            if ($madeAt !== false) {
                $found[$table] = $madeAt;
            }
        }
        ksort($found);

        return $found;
    }

    /**
     * A copy's name: the table's, the mark, the minute. Where that is longer than a name may
     * be, the table's part is cut and a piece of its hash keeps two long names apart.
     */
    private function nameFor(string $table, DateTimeImmutable $at): string
    {
        $suffix = self::MARK . $at->setTimezone(new DateTimeZone('UTC'))->format('YmdHi');
        $room   = self::LONGEST_NAME - strlen($suffix);

        if (strlen($table) > $room) {
            $table = substr($table, 0, $room - 9) . '_' . substr(hash('sha256', $table), 0, 8);
        }

        return $table . $suffix;
    }

    /** The table a copy is of: the registered one that would be given this name, or the name without its ending. */
    private function tableOf(string $copy, DateTimeImmutable $madeAt): string
    {
        foreach ($this->schemas->tableNames() as $name) {
            $table = $this->migrator->fullName($name);

            if ($this->nameFor($table, $madeAt) === $copy) {
                return $table;
            }
        }

        return (string) preg_replace(self::A_COPY, '', $copy);
    }

    private function goesAt(DateTimeImmutable $madeAt): DateTimeImmutable
    {
        return $madeAt->add(new DateInterval('P' . self::DAYS . 'D'));
    }
}
