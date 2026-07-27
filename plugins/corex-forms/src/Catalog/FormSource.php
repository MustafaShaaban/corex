<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Catalog;

defined('ABSPATH') || exit;

/**
 * Where a form in the catalog came from, and which one wins when two claim the same slug.
 *
 * The order is not cosmetic. A visual flow has a database identity, a version history, and an
 * editor; a code form has a class; an external entry has only whatever a third party handed us.
 * Preferring the richer, editable definition means a slug collision degrades to "the one you can
 * actually work on" rather than to whichever source happened to be read first.
 */
final class FormSource
{
    public const VISUAL_FLOW = 'visual_flow';
    public const CODE_FORM   = 'code_form';
    public const EXTERNAL    = 'external';

    /** Highest wins. @var array<string,int> */
    private const PRECEDENCE = [
        self::VISUAL_FLOW => 3,
        self::CODE_FORM   => 2,
        self::EXTERNAL    => 1,
    ];

    public static function isValid(string $source): bool
    {
        return isset(self::PRECEDENCE[$source]);
    }

    public static function precedence(string $source): int
    {
        return self::PRECEDENCE[$source] ?? 0;
    }
}
