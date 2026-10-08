<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/**
 * Writes an export as a PDF (spec 103, US8), with mPDF: the library that shapes and reorders
 * Arabic, which a document of people's answers has to do.
 *
 * What the document says and how it is set out is {@see PdfExportLayout}'s. This hands that to
 * the library a piece at a time and saves the file.
 */
final readonly class PdfExportWriter implements ExportWriter
{
    private const EXTENSION = 'pdf';
    private const CONTENT_TYPE = 'application/pdf';

    /** Where the library keeps the font tables it works out, inside the export directory. */
    private const WORKING_DIRECTORY = 'pdf-working';

    public function __construct(private PdfExportLayout $layout, private ExportDirectory $directory)
    {
    }

    /**
     * Whether a PDF can be written here: the library is installed, and PHP has the two extensions
     * it draws and measures with.
     */
    public static function supported(): bool
    {
        return class_exists(Mpdf::class) && extension_loaded('gd') && extension_loaded('mbstring');
    }

    public function write(ExportDocument $document, string $pathWithoutExtension): ExportFile
    {
        if (! self::supported()) {
            throw new RuntimeException('A PDF cannot be written on this server: it needs the PDF library and PHP\'s gd and mbstring extensions.');
        }

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'tempDir' => $this->workingDirectory(),
            'margin_top' => 26,
            'margin_bottom' => 20,
            'margin_left' => 12,
            'margin_right' => 12,
            'default_font' => 'dejavusans',
            'default_font_size' => 9,
            // The font is chosen by the script of the text, so an answer in Arabic, Hebrew or
            // Chinese is drawn in a font that has its letters.
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        if ($this->layout->rightToLeft()) {
            $pdf->SetDirectionality('rtl');
        }
        $pdf->SetTitle($document->title);
        $pdf->SetCreator('CoreX');
        $pdf->SetHTMLHeader($this->layout->header($document));
        $pdf->SetHTMLFooter($this->layout->footer());
        $pdf->WriteHTML($this->layout->styles(), HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($this->layout->opening($document), HTMLParserMode::HTML_BODY);

        $named = count($document->sheets) > 1;
        foreach ($document->sheets as $sheet) {
            foreach ($this->layout->sheet($sheet, $named) as $piece) {
                $pdf->WriteHTML($piece, HTMLParserMode::HTML_BODY);
            }
        }

        $path = $pathWithoutExtension . '.' . self::EXTENSION;
        $pdf->Output($path, Destination::FILE);

        return new ExportFile($path, self::EXTENSION, self::CONTENT_TYPE);
    }

    private function workingDirectory(): string
    {
        $path = $this->directory->path() . '/' . self::WORKING_DIRECTORY;
        if (! is_dir($path) && ! wp_mkdir_p($path)) {
            throw new RuntimeException('CoreX could not create the directory a PDF is worked out in.');
        }

        return $path;
    }
}
