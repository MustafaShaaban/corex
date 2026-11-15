<?php

/**
 * The Media library can be converted to WebP from the admin (spec 119, US-SET-06).
 *
 * Only `wp corex media regenerate-webp` converted images uploaded before WebP was on, and shared
 * hosts have no command line. On real WordPress, with real JPEGs and one upload whose file is gone,
 * through the service the Settings page's routes call.
 *
 * @package Corex\Tests\Integration\Media
 */

declare(strict_types=1);

use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Jobs\BoundedJob;
use Corex\Media\Conversion\MediaConversionRuns;
use Corex\Media\Conversion\MediaConversionService;
use Corex\Media\ImageCapability;
use Corex\Media\MediaSettings;
use Corex\Media\WebpConverter;
use Corex\Media\WebpMeta;
use Corex\Media\WebpRegenerator;
use Corex\Media\WpMediaRegenerationSource;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    if (! Boot::app()->container()->has(MediaConversionService::class) || ! function_exists('imagewebp')) {
        $this->markTestSkipped('Corex Media is not active here, or this PHP cannot write WebP.');
    }

    $this->signedInAs = get_current_user_id();
    $administrators   = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->admin      = (int) ($administrators[0] ?? 0);
    wp_set_current_user($this->admin);

    $this->conversion  = Boot::app()->container()->make(MediaConversionService::class);
    $this->runWas      = get_option(MediaConversionRuns::OPTION, null);
    $this->attachments = [];
    $this->files       = [];
});

afterEach(function () {
    // A run left going is cancelled, so the next test can start one.
    $this->conversion?->cancel();
    foreach ($this->attachments as $id) {
        wp_delete_attachment($id, true);
    }
    foreach ($this->files as $file) {
        foreach ([$file, (string) preg_replace('/\.jpe?g$/i', '.webp', $file)] as $left) {
            if (is_file($left)) {
                unlink($left);
            }
        }
    }
    $this->runWas === null ? delete_option(MediaConversionRuns::OPTION) : update_option(MediaConversionRuns::OPTION, $this->runWas, false);
    wp_set_current_user($this->signedInAs ?? 0);
});

/** An uploaded JPEG with no WebP copy; its file is removed again when `$keepFile` is false. */
function uploadedJpeg(object $test, bool $keepFile = true): int
{
    $uploads = wp_upload_dir();
    $file    = $uploads['path'] . '/corex-conversion-' . uniqid() . '.jpg';
    $image   = imagecreatetruecolor(64, 48);
    imagefilledrectangle($image, 0, 0, 63, 47, (int) imagecolorallocate($image, 200, 120, 40));
    imagejpeg($image, $file, 90);
    imagedestroy($image);

    $id = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => 'conversion', 'post_status' => 'inherit'], $file);
    $test->attachments[] = (int) $id;
    $test->files[]       = $file;

    if (! $keepFile) {
        unlink($file);
    }

    return (int) $id;
}

/** Advances the run the way the open page does, until it ends. */
function convertToTheEnd(MediaConversionService $conversion): ?array
{
    $run = $conversion->status();
    for ($step = 0; $step < 20 && ($run['active'] ?? false); $step++) {
        $run = $conversion->advance();
    }

    return $run;
}

it('converts the images uploaded before, skips none it should not, and lists a missing file', function () {
    $converted = [uploadedJpeg($this), uploadedJpeg($this)];
    uploadedJpeg($this, false);

    $started = $this->conversion->start($this->admin);
    expect($started['error'])->toBe('')
        ->and($started['run']['total'])->toBeGreaterThanOrEqual(3);

    $run = convertToTheEnd($this->conversion);

    expect($run['state'])->toBe(BoundedJob::STATE_COMPLETED)
        ->and($run['converted'])->toBeGreaterThanOrEqual(2)
        ->and($run['failed'])->toBeGreaterThanOrEqual(1)
        ->and($run['converted'] + $run['skipped'] + $run['failed'])->toBe($run['total'])
        ->and(array_column($run['failures'], 'reason'))->toContain('the file is missing')
        ->and($run['summary'])->toContain('Finished on');

    foreach ($converted as $id) {
        $original = (string) get_attached_file($id);
        expect(is_file((string) preg_replace('/\.jpg$/', '.webp', $original)))->toBeTrue()
            // Tracked as an upload's WebP is, so delivery's gate applies to it.
            ->and(get_post_meta($id, WebpMeta::META_KEY, true))->toBeArray()
            ->and(is_file($original))->toBeTrue();
    }

    $events = Boot::app()->container()->make(ActivityService::class)->query(['area' => 'settings'], 1, 5);
    expect(array_map(static fn ($event) => $event->kind, $events))->toContain('media.conversion_finished')
        ->toContain('media.conversion_started');
});

it('runs one conversion at a time, and stops one that is cancelled', function () {
    uploadedJpeg($this);

    expect($this->conversion->start($this->admin)['error'])->toBe('')
        ->and($this->conversion->start($this->admin)['error'])->toBe('conversion_running');

    $run = $this->conversion->cancel();

    expect($run['state'])->toBe(BoundedJob::STATE_CANCELLED)
        ->and($run['summary'])->toContain('Cancelled on')
        ->and($this->conversion->cancel())->toBeNull()
        ->and(Boot::app()->container()->make(ActivityService::class)->query(['area' => 'settings'], 1, 1)[0]->kind ?? '')
        ->toBe('media.conversion_cancelled');
});

it('counts an image a stopped step converted as converted, not skipped', function () {
    $id = uploadedJpeg($this);

    // What a step killed by the host's time limit leaves: the WebP written and tracked, and no
    // count saved for it.
    $original = (string) get_attached_file($id);
    $webp     = (string) preg_replace('/\.jpg$/', '.webp', $original);
    $image    = imagecreatefromjpeg($original);
    imagewebp($image, $webp, 82);
    imagedestroy($image);
    update_post_meta($id, WebpMeta::META_KEY, wp_slash(WebpMeta::measure($original, $webp, 82, 5.0)->toArray()));

    // The batch holding only this image, as the job asks for it.
    $ids    = get_posts(['post_type' => 'attachment', 'post_mime_type' => ['image/jpeg', 'image/png'], 'post_status' => 'inherit', 'fields' => 'ids', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC']);
    $offset = (int) array_search($id, array_map('intval', $ids), true);
    $settings   = MediaSettings::fromConfig(Boot::app()->container()->make(ConfigInterface::class));
    $capability = ImageCapability::detect();
    $source     = new WpMediaRegenerationSource(new WebpRegenerator($capability, $settings), new WebpConverter($settings->quality), $capability, $settings);

    expect($source->convertBatch($offset, 1, new DateTimeImmutable('-1 minute'))['succeeded'])->toBe(1)
        // An image that had its WebP before the run is skipped, as before.
        ->and($source->convertBatch($offset, 1, new DateTimeImmutable('+1 minute'))['succeeded'])->toBe(0)
        ->and($source->convertBatch($offset, 1)['succeeded'])->toBe(0);
});

it('answers the latest run when the page is opened again', function () {
    uploadedJpeg($this);
    $this->conversion->start($this->admin);
    $ended = convertToTheEnd($this->conversion);

    expect($this->conversion->status())->toBe($ended);
});
