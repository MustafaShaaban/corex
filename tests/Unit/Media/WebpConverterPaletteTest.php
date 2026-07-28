<?php

/**
 * WebP conversion of an indexed-colour (palette) PNG — issue #142.
 *
 * `imagewebp()` refuses a palette image with `Palette image not supported by webp`, raised as an
 * **E_ERROR**, not a Throwable. `WebpConverter::convert()` wraps the call in `catch (Throwable)`,
 * so the fail-safe could not fire and the fatal ended the request — and because the conversion runs
 * on `wp_generate_attachment_metadata`, the request it ended was a media upload. A palette PNG is
 * what most design tools export a flat-colour logo as, so this was a routine upload.
 *
 * These tests run the real converter over real PNG files. There is nothing to mock: the whole
 * defect lived in what GD does with a particular kind of image, and a doubled GD would have had the
 * bug written out of it.
 *
 * @package Corex\Tests\Unit\Media
 */

declare(strict_types=1);

use Corex\Media\ConversionPlan;
use Corex\Media\WebpConverter;

/** Alpha of a pixel, 0 (opaque) to 127 (fully transparent). */
function webpAlphaAt(string $file, int $x, int $y): int
{
    $image = imagecreatefromwebp($file);
    $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
    imagedestroy($image);

    return $alpha;
}

beforeEach(function () {
    if (! function_exists('imagewebp') || ! function_exists('imagecreatefromwebp')) {
        $this->markTestSkipped('GD WebP support is required to exercise the GD conversion path.');
    }

    $this->fixtures = dirname(__DIR__, 2) . '/Fixtures/Media';
    $this->workdir  = sys_get_temp_dir() . '/corex-webp-' . bin2hex(random_bytes(6));
    mkdir($this->workdir, 0777, true);

    // The converter derives its source from the output path by stripping `.webp`, so the source has
    // to sit beside where the WebP will be written.
    $this->plan = function (string $fixture): ConversionPlan {
        copy($this->fixtures . '/' . $fixture, $this->workdir . '/' . $fixture);

        $reflection = new ReflectionClass(ConversionPlan::class);
        $plan       = $reflection->newInstanceWithoutConstructor();
        foreach (['convert' => true, 'format' => 'webp', 'outputPath' => $this->workdir . '/' . pathinfo($fixture, PATHINFO_FILENAME) . '.webp'] as $property => $value) {
            $slot = $reflection->getProperty($property);
            $slot->setValue($plan, $value);
        }

        return $plan;
    };
});

afterEach(function () {
    foreach (glob($this->workdir . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($this->workdir);
});

it('converts an indexed-colour PNG instead of ending the request', function () {
    // Against the unfixed converter this test does not fail — the PHP process dies mid-run, taking
    // the suite with it. That is the point: an E_ERROR is not a failed assertion.
    $plan = ($this->plan)('palette-with-alpha.png');

    expect((new WebpConverter(82))->convert($plan, 'image/png'))->toBeTrue()
        ->and(is_file($plan->outputPath))->toBeTrue()
        ->and(filesize($plan->outputPath))->toBeGreaterThan(0);
});

it('keeps a palette PNG transparent through the conversion', function () {
    // The fixture is a PNG-8 whose transparency is a transparent palette index, which is how a
    // logo exported from a design tool actually stores it — not an alpha channel.
    $plan = ($this->plan)('palette-with-alpha.png');

    (new WebpConverter(82))->convert($plan, 'image/png');

    expect(webpAlphaAt($plan->outputPath, 0, 0))->toBe(127)
        ->and(webpAlphaAt($plan->outputPath, 32, 32))->toBe(0);
});

it('still converts a truecolour PNG, so the promotion changes nothing for the common case', function () {
    $plan = ($this->plan)('truecolor.png');

    expect((new WebpConverter(82))->convert($plan, 'image/png'))->toBeTrue()
        ->and(is_file($plan->outputPath))->toBeTrue();
});

it('keeps a truecolour PNG with an alpha channel transparent', function () {
    $plan = ($this->plan)('truecolor-with-alpha.png');

    (new WebpConverter(82))->convert($plan, 'image/png');

    expect(webpAlphaAt($plan->outputPath, 0, 0))->toBe(127)
        ->and(webpAlphaAt($plan->outputPath, 32, 32))->toBe(0);
});
