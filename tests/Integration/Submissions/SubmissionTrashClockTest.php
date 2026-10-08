<?php

/**
 * The trash's own clock, in WordPress (spec 105, US3; FR-017 to FR-020).
 *
 * WordPress empties its trash by itself, for every post type. What only WordPress can show is
 * that a submission leaves the trash when CoreX's clock says and not when WordPress's does, that
 * it goes with the file uploaded with it, and that a submission deleted by something else takes
 * its file too.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Retention\RetentionSweep;
use Corex\Config\Submissions\SubmissionTrashRetention;
use Corex\Config\Submissions\SubmissionTrashStore;
use Corex\Config\Submissions\WpSubmissionTrashStore;
use Corex\Security\Upload\ProtectedUploads;

beforeEach(function () {
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $this->made = [];
    $this->container = Boot::app()->container();
    $this->trash = new WpSubmissionTrashStore(new WpSubmissionsReader());
    $this->retention = $this->container->make(SubmissionTrashRetention::class);
    $this->previousDays = get_option(SubmissionTrashRetention::OPTION, null);
    $this->administrator = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
});

afterEach(function () {
    foreach ($this->made as $id) {
        get_post_type($id) === 'attachment' ? wp_delete_attachment($id, true) : wp_delete_post($id, true);
    }
    $this->previousDays === null
        ? delete_option(SubmissionTrashRetention::OPTION)
        : update_option(SubmissionTrashRetention::OPTION, $this->previousDays, false);
});

function clockSubmission(array $meta = []): int
{
    return (int) wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'Clock submission',
        'meta_input' => $meta + [
            'corex_form_slug' => 'corex-clock-test',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'none',
            'corex_submission_updated_at' => '2026-10-01T09:30:00+00:00',
        ],
    ]);
}

function clockUpload(): int
{
    $path = ProtectedUploads::ensure() . '/corex-clock-test-' . wp_generate_password(8, false) . '.txt';
    file_put_contents($path, 'A file.');
    $attachment = (int) wp_insert_attachment(['post_title' => 'File', 'post_status' => 'private', 'post_mime_type' => 'text/plain'], $path);
    update_post_meta($attachment, '_corex_protected', '1');
    update_post_meta($attachment, '_corex_upload_context', 'form-file');

    return $attachment;
}

/** Trash a submission as CoreX does, some days ago. */
function trashedDaysAgo(WpSubmissionTrashStore $trash, int $id, int $actor, int $days): void
{
    $trash->trash($id, $actor, SubmissionTrashStore::VIA_INBOX);
    update_post_meta($id, 'corex_trashed_at', gmdate(DATE_ATOM, time() - ($days * DAY_IN_SECONDS)));
}

it('keeps a trashed submission 30 days where the site has said nothing, and as long as it says', function () {
    delete_option(SubmissionTrashRetention::OPTION);

    expect($this->retention->retentionDays())->toBe(30)
        ->and($this->retention->setDays(7))->toBe(7)
        ->and($this->retention->retentionDays())->toBe(7);
});

it('deletes what the trash has kept long enough, with its file, in the daily sweep, and leaves what it has not', function () {
    $this->made[] = $file = clockUpload();
    $path = (string) get_attached_file($file);
    $this->made[] = $old = clockSubmission(['corex_field_file' => (string) $file]);
    $this->made[] = $recent = clockSubmission();
    trashedDaysAgo($this->trash, $old, $this->administrator, 40);
    trashedDaysAgo($this->trash, $recent, $this->administrator, 2);
    $this->retention->setDays(30);

    $this->container->make(RetentionSweep::class)->apply(new DateTimeImmutable('now'));

    $entry = $this->container->make(Corex\Activity\ActivityRepository::class)
        ->query(['kind' => 'submission.deleted'], 1, 1)[0] ?? null;

    expect(get_post($old))->toBeNull()
        ->and(is_file($path))->toBeFalse()
        ->and(get_post_status($recent))->toBe('trash')
        ->and($entry?->context['by'] ?? null)->toBe('expiry')
        ->and($entry?->actorKind)->toBe('cron');
});

it('deletes nothing from the trash when the site says never', function () {
    $this->made[] = $old = clockSubmission();
    trashedDaysAgo($this->trash, $old, $this->administrator, 400);
    $this->retention->setDays(0);

    $this->container->make(RetentionSweep::class)->apply(new DateTimeImmutable('now'));
    wp_scheduled_delete();

    expect(get_post_status($old))->toBe('trash');
});

it('keeps WordPress’s own clean-up from deleting a submission, and takes it onto CoreX’s clock', function () {
    // As the retention panel trashed one before the inbox had a trash: WordPress's way, with
    // WordPress's date, long enough ago for WordPress to delete it today. Written as that left
    // it, because trashing one that way now is adopted on the spot (the next test).
    $this->made[] = $id = clockSubmission();
    wp_update_post(['ID' => $id, 'post_status' => 'trash']);
    $trashedAt = time() - (DAY_IN_SECONDS * (EMPTY_TRASH_DAYS + 5));
    update_post_meta($id, '_wp_trash_meta_time', $trashedAt);
    update_post_meta($id, '_wp_trash_meta_status', 'private');

    // WordPress runs its clean-up as this action, from its daily schedule.
    do_action('wp_scheduled_delete');

    expect(get_post_status($id))->toBe('trash');

    $adopted = $this->trash->adoptWordPressTrash(50);

    expect($adopted)->toBeGreaterThanOrEqual(1)
        ->and(get_post_meta($id, 'corex_trashed_at', true))->toBe(gmdate(DATE_ATOM, $trashedAt))
        ->and(get_post_meta($id, 'corex_trashed_via', true))->toBe('wordpress')
        ->and(metadata_exists('post', $id, '_wp_trash_meta_time'))->toBeFalse()
        // And from then on it is due by CoreX's clock, on the date WordPress had for it.
        ->and($this->trash->trashedBefore(new DateTimeImmutable('-30 days'), 500))->toContain($id);
});

it('takes a submission onto its own clock the moment something trashes it WordPress’s way', function () {
    $this->made[] = $id = clockSubmission();

    wp_trash_post($id);

    expect(get_post_status($id))->toBe('trash')
        ->and(get_post_meta($id, 'corex_trashed_via', true))->toBe('wordpress')
        ->and(strtotime((string) get_post_meta($id, 'corex_trashed_at', true)))->toBeGreaterThan(time() - 60)
        // With no date of WordPress's left on it, WordPress's clean-up never looks at it again.
        ->and(metadata_exists('post', $id, '_wp_trash_meta_time'))->toBeFalse();
});

it('removes the file uploaded with a submission when something else deletes the submission', function () {
    $this->made[] = $file = clockUpload();
    $path = (string) get_attached_file($file);
    $this->made[] = $id = clockSubmission(['corex_field_file' => (string) $file]);

    // Not the inbox: a command, another plugin, anything that calls WordPress directly.
    wp_delete_post($id, true);

    expect(get_post($id))->toBeNull()
        ->and(get_post($file))->toBeNull()
        ->and(is_file($path))->toBeFalse();
});
