<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use InvalidArgumentException;

final readonly class DataExportArtifact
{
    private function __construct(public string $format, public string $content)
    {
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            throw new InvalidArgumentException('Data export artifact format is invalid.');
        }
    }

    public static function start(string $format, string $content = ''): self
    {
        return new self($format, $content);
    }
}
