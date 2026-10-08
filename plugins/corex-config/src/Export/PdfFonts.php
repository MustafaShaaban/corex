<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

/**
 * The fonts a PDF can be written with where CoreX runs (spec 103, US8).
 *
 * The PDF library lists forty fonts, one or more for each script it can write, and picks by the
 * text. It does not look whether a font's file is there until it needs it, and then it stops the
 * whole document. A shared-host package ships a few of the fonts, because the whole set is most
 * of the package's weight. So the library is given the list of what is installed, and writes a
 * script it has no font for in its default font: the letters that font lacks print as empty
 * boxes, and the export is still written.
 */
final readonly class PdfFonts
{
    /** The files a font is made of, by the names the library gives its styles. */
    private const STYLES = ['R', 'B', 'I', 'BI'];

    /**
     * @param list<string> $directories Where font files are looked for.
     */
    public function __construct(private array $directories)
    {
    }

    /** The fonts in the directory the PDF library ships its own in. */
    public static function shippedWithLibrary(): self
    {
        return new self((new ConfigVariables())->getDefaults()['fontDir']);
    }

    /** @return list<string> */
    public function directories(): array
    {
        return $this->directories;
    }

    /**
     * Every font the library lists, installed or not.
     *
     * @return array<string,array<string,mixed>>
     */
    public function all(): array
    {
        return (new FontVariables())->getDefaults()['fontdata'];
    }

    /**
     * The library's fonts that are installed.
     *
     * @return array<string,array<string,mixed>>
     */
    public function installed(): array
    {
        return $this->among($this->all());
    }

    /**
     * Of the fonts given, the ones whose every file is in a directory. A font with its regular
     * file and without its bold is left out: a bold heading in it would stop the document.
     *
     * @param array<string,array<string,mixed>> $fonts As the library lists them.
     *
     * @return array<string,array<string,mixed>>
     */
    public function among(array $fonts): array
    {
        return array_filter($fonts, fn (array $font): bool => $this->has($font));
    }

    /** @param array<string,mixed> $font */
    private function has(array $font): bool
    {
        foreach (self::STYLES as $style) {
            if (isset($font[$style]) && ! $this->holds((string) $font[$style])) {
                return false;
            }
        }

        return true;
    }

    private function holds(string $file): bool
    {
        foreach ($this->directories as $directory) {
            if (is_file($directory . '/' . $file)) {
                return true;
            }
        }

        return false;
    }
}
