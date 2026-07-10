<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;
interface MigrationAwareDataSource
{
    public function migrationProvider(): MigrationProvider;
}
