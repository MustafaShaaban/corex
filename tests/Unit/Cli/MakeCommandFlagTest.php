<?php

/**
 * How `wp corex make:*` reads a flag (spec 136, FR-014): `--records` is on, `--no-records` and
 * `--records=false` are off, and a flag left out is off.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Commands\MakeCommand;

it('FR-014: reads a flag as on only when it is given and not turned off', function (array $assoc, bool $on) {
    expect(MakeCommand::flag($assoc, 'records'))->toBe($on);
})->with([
    'given'           => [['records' => true], true],
    'given as text'   => [['records' => 'true'], true],
    'given as 1'      => [['records' => '1'], true],
    'left out'        => [[], false],
    '--no-records'    => [['records' => false], false],
    '--records=false' => [['records' => 'false'], false],
    '--records=0'     => [['records' => '0'], false],
    '--records=no'    => [['records' => 'no'], false],
]);
