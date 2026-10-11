<?php

/**
 * Unit test: make:email-design scaffolds a site's own email design that works as it is written
 * (spec 106: FR-019).
 *
 * The generator is how a client's developer meets this API, so what it writes is loaded and
 * drawn here, not only read: a starting point that does not render is worse than none.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\EmailDesignGenerator;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Support\Naming;
use Corex\Email\Design\EmailBrand;
use Corex\Email\Design\EmailContent;
use Corex\Email\Design\EmailDesign;

/**
 * @return array{path:string,code:string} Where the design was written, and what was written.
 */
function generatedEmailDesign(string $className, string $namespace): array
{
    $base = sys_get_temp_dir() . '/corex_email_design_' . uniqid('', true);
    mkdir($base);
    $engine = new GeneratorEngine(
        new StubRenderer(),
        new Naming(),
        new GeneratorContext($base, $namespace, 'corex'),
        dirname(__DIR__, 3) . '/packages/cli/stubs',
    );
    $path = $engine->generate(new EmailDesignGenerator(), $className)->path;

    return ['path' => $path, 'code' => (string) file_get_contents($path)];
}

it('writes the design into the site plugin, under its own namespace, with nothing left to fill in', function () {
    $written = generatedEmailDesign('Acme', 'AcmeSite');

    expect(str_replace('\\', '/', $written['path']))->toEndWith('/Mail/AcmeEmailDesign.php')
        ->and($written['code'])->not->toContain('{{')
        ->and($written['code'])->toContain('namespace AcmeSite\\Mail;')
        ->and($written['code'])->toContain('final class AcmeEmailDesign implements EmailDesign')
        ->and($written['code'])->toContain("defined('ABSPATH') || exit;");
});

it('says how the design is registered, with the class it wrote', function () {
    expect(generatedEmailDesign('Acme', 'AcmeSite')['code'])
        ->toContain("add_filter('corex_email_design', static fn () => new AcmeEmailDesign());");
});

it('writes a design that draws an email as it is, holding the message', function () {
    // A namespace of its own, so loading this file cannot meet another test's class.
    $written = generatedEmailDesign('Drawn', 'GeneratedDesignProbe');
    require_once $written['path'];
    $class = 'GeneratedDesignProbe\\Mail\\DrawnEmailDesign';
    $design = new $class();

    $html = $design->render(
        new EmailContent('Re: your <message>', '<p>Thanks, Sam.</p><ul><li>One</li></ul><p><a href="https://example.com">here</a></p>', 'sam@example.com'),
        EmailBrand::standard('Acme & Sons', 'https://acme.test/', '', 'rtl', 'ar'),
    );

    expect($design)->toBeInstanceOf(EmailDesign::class)
        ->and($html)->toStartWith('<!DOCTYPE html><html lang="ar" dir="rtl">')
        ->and($html)->toContain('<title>Re: your &lt;message&gt;</title>')
        ->and($html)->toContain('Acme &amp; Sons')
        ->and(substr_count($html, 'Thanks, Sam.'))->toBe(1)
        ->and($html)->toContain('href="https://example.com">here</a>')
        ->and($html)->toContain('text-align:right')
        ->and(substr_count($html, '<table'))->toBe(substr_count($html, '</table>'));
});
