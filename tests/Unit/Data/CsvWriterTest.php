<?php

/**
 * Unit tests for the CSV export writer (spec 045: US2, FR-005).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Config\Data\CsvWriter;

beforeEach(function () {
    $this->writer  = new CsvWriter();
    $this->columns = [
        ['id' => 'date', 'label' => 'Date'],
        ['id' => 'form', 'label' => 'Form'],
    ];
});

it('writes a header row from the column labels', function () {
    $csv = $this->writer->write($this->columns, []);

    expect($csv)->toBe("Date,Form\r\n");
});

it('writes one escaped row per record', function () {
    $csv = $this->writer->write($this->columns, [
        ['date' => '2026-06-13', 'form' => 'contact'],
    ]);

    expect($csv)->toBe("Date,Form\r\n2026-06-13,contact\r\n");
});

it('quotes values with commas, quotes, or newlines and doubles embedded quotes', function () {
    $csv = $this->writer->write(
        [['id' => 'v', 'label' => 'V']],
        [
            ['v' => 'a,b'],
            ['v' => 'say "hi"'],
            ['v' => "line1\nline2"],
        ],
    );

    expect($csv)->toBe("V\r\n\"a,b\"\r\n\"say \"\"hi\"\"\"\r\n\"line1\nline2\"\r\n");
});

it('includes only the declared columns, ignoring extra keys', function () {
    $csv = $this->writer->write($this->columns, [
        ['date' => 'd', 'form' => 'f', 'secret' => 'LEAK'],
    ]);

    expect($csv)->not->toContain('LEAK')
        ->and($csv)->toBe("Date,Form\r\nd,f\r\n");
});

it('renders a missing value as empty', function () {
    $csv = $this->writer->write($this->columns, [['date' => 'd']]);

    expect($csv)->toBe("Date,Form\r\nd,\r\n");
});

it('neutralises a formula-injection value with a leading quote', function () {
    $csv = $this->writer->write([['id' => 'v', 'label' => 'V']], [['v' => '=1+2']]);

    expect($csv)->toBe("V\r\n'=1+2\r\n");
});
