<?php

/**
 * CoreX Media settings + WebP regeneration planning (spec 061). MediaSettings reads Config dot-keys
 * with filterable, sanitised defaults; ConversionPlan honours them (enable + per-format); the
 * regenerator decides convert / skip-exists / skip-unsupported; MediaSupport formats server support.
 * Pure — no filesystem writes, no real WordPress.
 *
 * @package Corex\Tests\Unit\Media
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Media\ConversionPlan;
use Corex\Media\ImageCapability;
use Corex\Media\MediaImage;
use Corex\Media\MediaSettings;
use Corex\Media\MediaSupport;
use Corex\Media\PictureRenderer;
use Corex\Media\WebpRegenerator;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value) => $value);
    Functions\when('__')->returnArg(1);
});

/** A ConfigInterface stub backed by an array of dot-keys. */
function mediaConfig(array $values): ConfigInterface
{
    return new class ($values) implements ConfigInterface {
        public function __construct(private array $values)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->values);
        }
    };
}

it('defaults to enabled, quality 82, both formats on', function () {
    $s = MediaSettings::defaults();
    expect($s->enabled)->toBeTrue()
        ->and($s->quality)->toBe(82)
        ->and($s->convertJpeg)->toBeTrue()
        ->and($s->convertPng)->toBeTrue()
        ->and($s->convertibleMimes())->toBe(['image/jpeg', 'image/png']);
});

it('reads config and clamps quality into 1-100', function () {
    expect(MediaSettings::fromConfig(mediaConfig(['media.webp.quality' => '60']))->quality)->toBe(60)
        ->and(MediaSettings::fromConfig(mediaConfig(['media.webp.quality' => '999']))->quality)->toBe(100)
        ->and(MediaSettings::fromConfig(mediaConfig(['media.webp.quality' => '0']))->quality)->toBe(1)
        ->and(MediaSettings::fromConfig(mediaConfig(['media.webp.quality' => 'abc']))->quality)->toBe(82);
});

it('coerces string/bool flags and reflects per-format toggles in convertibleMimes', function () {
    $off = MediaSettings::fromConfig(mediaConfig([
        'media.webp.convert_jpeg' => '0',
        'media.webp.convert_png'  => '1',
    ]));
    expect($off->convertJpeg)->toBeFalse()
        ->and($off->convertPng)->toBeTrue()
        ->and($off->convertibleMimes())->toBe(['image/png']);

    $disabled = MediaSettings::fromConfig(mediaConfig(['media.webp.enabled' => 'false']));
    expect($disabled->enabled)->toBeFalse();
});

it('plans no conversion when disabled, and only the enabled formats', function () {
    $cap = new ImageCapability(true, false, true, false);

    $disabled = new MediaSettings(false, 82, true, true);
    expect(ConversionPlan::for('/u/a.jpg', 'image/jpeg', $cap, $disabled)->convert)->toBeFalse();

    $pngOnly = new MediaSettings(true, 82, false, true);
    expect(ConversionPlan::for('/u/a.jpg', 'image/jpeg', $cap, $pngOnly)->convert)->toBeFalse()
        ->and(ConversionPlan::for('/u/a.png', 'image/png', $cap, $pngOnly)->convert)->toBeTrue();
});

it('regenerator converts missing siblings, skips existing and unsupported, and counts', function () {
    $cap = new ImageCapability(true, false, true, false);
    $regen = new WebpRegenerator($cap, MediaSettings::defaults());

    $result = $regen->plan([
        ['id' => 1, 'path' => '/u/a.jpg', 'mime' => 'image/jpeg', 'webp_exists' => false],
        ['id' => 2, 'path' => '/u/b.png', 'mime' => 'image/png', 'webp_exists' => true],
        ['id' => 3, 'path' => '/u/c.gif', 'mime' => 'image/gif', 'webp_exists' => false],
    ]);

    $byId = array_column($result['actions'], 'action', 'id');
    expect($byId)->toBe([1 => 'convert', 2 => 'skip-exists', 3 => 'skip-unsupported'])
        ->and($result['counts'])->toBe(['convert' => 1, 'skipped' => 2, 'total' => 3]);
});

it('emits a <picture> for a URL with a WebP sibling, and a plain <img> without one', function () {
    Functions\when('esc_url')->returnArg(1);
    Functions\when('esc_attr')->returnArg(1);

    $dir = sys_get_temp_dir() . '/corex-media-' . uniqid();
    mkdir($dir, 0777, true);
    Functions\when('wp_get_upload_dir')->justReturn(['basedir' => $dir, 'baseurl' => 'http://acme.local/uploads']);

    $media = new MediaImage(new PictureRenderer());

    // no sibling on disk → plain <img>
    $plain = $media->pictureForUrl('http://acme.local/uploads/hero.jpg', 'Hero');
    expect($plain)->toContain('<img ')->not->toContain('<picture>');

    // create the sibling → <picture> with a WebP source
    file_put_contents($dir . '/hero.webp', 'x');
    $picture = $media->pictureForUrl('http://acme.local/uploads/hero.jpg', 'Hero');
    expect($picture)->toContain('<picture>')
        ->toContain('type="image/webp"')
        ->toContain('http://acme.local/uploads/hero.webp');

    @unlink($dir . '/hero.webp');
    @rmdir($dir);
});

it('summarises server support including uploads writability', function () {
    $cap = new ImageCapability(true, false, true, false);

    $writable = (new MediaSupport($cap, static fn (): bool => true))->summary();
    expect($writable)->toContain('GD: yes')
        ->toContain('Imagick: no')
        ->toContain('WebP encode: yes')
        ->toContain('Uploads writable: yes');

    $notWritable = (new MediaSupport($cap, static fn (): bool => false))->summary();
    expect($notWritable)->toContain('Uploads writable: no');
});
