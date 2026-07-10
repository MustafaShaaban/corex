<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;
use Corex\Operations\OperationResult;

interface MigrationProvider
{
    /** @return list<MigrationDefinition> */
    public function definitions(): array;
    public function snapshot(MigrationDefinition $definition): string;
    public function execute(MigrationDefinition $definition, string $snapshotId, bool $rollback): OperationResult;
}
