<?php

/**
 * A PDF written where most of the library's fonts are not installed (spec 103, US8).
 *
 * The shared-host package ships a few of the PDF library's fonts: the whole set is 87MB of a
 * package somebody uploads by hand. With a font's file missing the library stopped the export
 * with "Cannot find TTF TrueType font file" as soon as an answer was written in that font's
 * script. The writer tells the library about the fonts that are there, and the export is written:
 * a script without a font prints as empty boxes, and the workbook and the CSV still hold it.
 *
 * Written for real, as the other PDF test is: the unit suite's harness wraps file streams and
 * the library's font reads come back short through it.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Export\ExportCell;
use Corex\Config\Export\ExportDirectory;
use Corex\Config\Export\ExportDocument;
use Corex\Config\Export\ExportSheet;
use Corex\Config\Export\PdfExportLayout;
use Corex\Config\Export\PdfExportWriter;
use Corex\Config\Export\PdfFonts;

beforeEach(function () {
    if (! PdfExportWriter::supported()) {
        $this->markTestSkipped('The PDF library or the PHP extensions it needs are not installed.');
    }

    $this->work = untrailingslashit(get_temp_dir()) . '/corex-few-fonts-' . wp_generate_password(8, false);
    wp_mkdir_p($this->work . '/fonts');

    // One family of the forty the library lists: the default one.
    $shipped = PdfFonts::shippedWithLibrary();
    foreach ($shipped->among(['dejavusans' => $shipped->all()['dejavusans']])['dejavusans'] as $style => $file) {
        if (in_array($style, ['R', 'B', 'I', 'BI'], true)) {
            copy($shipped->directories()[0] . '/' . $file, $this->work . '/fonts/' . $file);
        }
    }
});

afterEach(function () {
    if (! isset($this->work)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->work, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->work);
});

it('writes a PDF with the fonts that are installed, whatever script an answer is in', function () {
    $work = $this->work;
    $directory = new class ($work) implements ExportDirectory {
        public function __construct(private string $path)
        {
        }

        public function path(): string
        {
            return $this->path;
        }
    };
    $writer = new PdfExportWriter(
        Boot::app()->container()->make(PdfExportLayout::class),
        $directory,
        new PdfFonts([$work . '/fonts']),
    );
    // Arabic has letters in the font that is there. Chinese, Korean, Thai and Hindi have none,
    // and each has a font of its own in the library's list whose file is not here.
    $answers = ['A website, please.', 'أريد موقعاً لشركتي.', '我想要一个网站', '웹사이트를 원합니다', 'ต้องการเว็บไซต์', 'मुझे एक वेबसाइट चाहिए'];
    $document = new ExportDocument('Contact submissions', [
        new ExportSheet('Contact', ['Message'], array_map(
            static fn (string $answer): array => [ExportCell::text($answer)],
            $answers,
        )),
    ]);

    $file = $writer->write($document, $work . '/few-fonts');

    expect($file->extension)->toBe('pdf')
        ->and(substr((string) file_get_contents($file->path), 0, 5))->toBe('%PDF-')
        ->and(filesize($file->path))->toBeGreaterThan(10000);
});
