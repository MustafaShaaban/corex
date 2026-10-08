<?php

/**
 * The fonts a PDF can be written with (spec 103, US8).
 *
 * The PDF library lists forty fonts and stops with "Cannot find TTF TrueType font file" when the
 * text asks for one whose file is not there. A shared-host package ships a few of them (the
 * whole set is 87MB of a package uploaded by hand), so the library is told only about the fonts
 * that are present, and writes the rest of the text in the font it has.
 *
 * @package Corex\Tests\Unit\Config\Export
 */

declare(strict_types=1);

use Corex\Config\Export\PdfFonts;

/** @param list<string> $files */
function pdfFontDirectory(array $files): string
{
    $directory = sys_get_temp_dir() . '/corex-pdf-fonts-' . bin2hex(random_bytes(6));
    mkdir($directory);
    foreach ($files as $file) {
        touch($directory . '/' . $file);
    }

    return $directory;
}

const PDF_FONT_LIST = [
    'dejavusans' => ['R' => 'DejaVuSans.ttf', 'B' => 'DejaVuSans-Bold.ttf', 'useOTL' => 0xFF],
    'xbriyaz' => ['R' => 'XB Riyaz.ttf', 'B' => 'XB RiyazBd.ttf', 'useOTL' => 0xFF],
    'sun-exta' => ['R' => 'Sun-ExtA.ttf', 'sip-ext' => 'sun-extb'],
];

it('offers the fonts whose every file is there, and none of the others', function () {
    $fonts = new PdfFonts([pdfFontDirectory(['DejaVuSans.ttf', 'DejaVuSans-Bold.ttf', 'XB Riyaz.ttf'])]);

    // XB Riyaz has its regular file and not its bold: a bold Arabic heading would stop the export.
    expect(array_keys($fonts->among(PDF_FONT_LIST)))->toBe(['dejavusans']);
});

it('finds a font in any of the directories it is given', function () {
    $fonts = new PdfFonts([
        pdfFontDirectory(['DejaVuSans.ttf', 'DejaVuSans-Bold.ttf']),
        pdfFontDirectory(['Sun-ExtA.ttf']),
    ]);

    expect(array_keys($fonts->among(PDF_FONT_LIST)))->toBe(['dejavusans', 'sun-exta']);
});

it('keeps what the library says of a font it offers', function () {
    $fonts = new PdfFonts([pdfFontDirectory(['DejaVuSans.ttf', 'DejaVuSans-Bold.ttf'])]);

    expect($fonts->among(PDF_FONT_LIST)['dejavusans'])->toBe(PDF_FONT_LIST['dejavusans']);
});
