<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;
interface MigrationPreviewStore
{
    public function issue(
        int $actorId,
        string $action,
        string $sourceKey,
        MigrationDefinition $definition,
        int $runId = 0,
    ): MigrationPreview;
    public function consume(string $token, int $actorId): ?MigrationPreview;
}
