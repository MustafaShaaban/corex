<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * A fluent custom-table definition that produces a `CREATE TABLE` statement. Pure:
 * it builds SQL only; running it is the migrator's job. Columns are NOT NULL by
 * default; pass $nullable to allow null.
 */
final class Table
{
    /**
     * @var list<string>
     */
    private array $columns = [];

    /** @var list<string> */
    private array $indexes = [];

    public function __construct(public readonly string $name)
    {
    }

    public function id(): self
    {
        $this->columns[] = 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT';

        return $this;
    }

    public function string(string $name, int $length = 255, bool $nullable = false): self
    {
        return $this->add(sprintf('%s VARCHAR(%d)', $name, $length), $nullable);
    }

    public function integer(string $name, bool $nullable = false): self
    {
        return $this->add(sprintf('%s BIGINT', $name), $nullable);
    }

    public function boolean(string $name, bool $nullable = false): self
    {
        return $this->add(sprintf('%s TINYINT(1)', $name), $nullable);
    }

    public function text(string $name, bool $nullable = false): self
    {
        return $this->add(sprintf('%s LONGTEXT', $name), $nullable);
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2, bool $nullable = false): self
    {
        return $this->add(sprintf('%s DECIMAL(%d,%d)', $name, $precision, $scale), $nullable);
    }

    public function datetime(string $name, bool $nullable = false): self
    {
        return $this->add(sprintf('%s DATETIME', $name), $nullable);
    }

    public function timestamps(): self
    {
        return $this->datetime('created_at', nullable: true)->datetime('updated_at', nullable: true);
    }

    /** @param list<string> $columns */
    public function index(string $name, array $columns, bool $unique = false): self
    {
        $this->assertIdentifier($name);

        if ($columns === []) {
            throw new \InvalidArgumentException('A table index requires at least one column.');
        }

        foreach ($columns as $column) {
            $this->assertIdentifier($column);
        }

        $this->indexes[] = sprintf(
            '%sKEY %s (%s)',
            $unique ? 'UNIQUE ' : '',
            $name,
            implode(', ', $columns),
        );

        return $this;
    }

    public function createSql(string $fullTableName, string $charsetCollate): string
    {
        // dbDelta-friendly format: one column per line, two spaces before the key.
        return sprintf(
            "CREATE TABLE %s (\n\t%s,\n\tPRIMARY KEY  (id)\n) %s;",
            $fullTableName,
            implode(",\n\t", [...$this->columns, ...$this->indexes]),
            $charsetCollate
        );
    }

    private function add(string $definition, bool $nullable): self
    {
        $this->columns[] = $definition . ($nullable ? ' NULL' : ' NOT NULL');

        return $this;
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException(sprintf('Unsafe schema identifier: "%s".', $identifier));
        }
    }
}
