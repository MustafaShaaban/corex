<?php

/**
 * Integration test: the application data path on real ./wp (spec 014 US2: FR-003, SC-002).
 * The provider creates the corex_applications table on init; this stores an application
 * through the real custom-table store and confirms the notification path. Mail is intercepted.
 *
 * **The attachment store is substituted, and that is deliberate.** Since spec 081 the service
 * stores the CV before writing the row, and `wp_handle_upload()` refuses anything that is not a
 * real HTTP upload — `is_uploaded_file()` is false for every file a test process can create, by
 * design. Faking it would mean weakening the check in production to make a test pass. What this
 * file is for is the row: that a valid application lands in the real custom table with the id it
 * was given. `tests/Unit/Careers/CareersTest.php` covers the ordering, and the real move is
 * exercised by `AttachmentStoreTest`.
 *
 * @package Corex\Tests\Integration\Careers
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Careers\Application\ApplicationRepository;
use Corex\Careers\Application\ApplicationService;
use Corex\Careers\Application\ApplicationStore;
use Corex\Mail\Mailer;
use Corex\Security\Upload\AttachmentResult;
use Corex\Security\Upload\AttachmentStorage;
use Corex\Security\Upload\UploadValidator;

it('stores a valid application through the real custom table', function () {
    add_filter('pre_wp_mail', '__return_true');

    $container = Boot::app()->container();

    $storage = new class () implements AttachmentStorage {
        public function store(array $file, string $context = ''): AttachmentResult
        {
            return AttachmentResult::stored(4242);
        }

        public function forget(int $attachmentId): bool
        {
            return true;
        }
    };

    $service = new ApplicationService(
        $container->make(ApplicationStore::class),
        new UploadValidator(['application/pdf' => ['pdf']], 5 * 1024 * 1024),
        $storage,
        $container->make(Mailer::class),
        'hr@example.com',
    );

    $cv     = ['name' => 'cv.pdf', 'type' => 'application/pdf', 'size' => 100_000, 'error' => UPLOAD_ERR_OK];
    $result = $service->apply(0, ['name' => 'Applicant', 'email' => 'a-' . uniqid() . '@example.com'], $cv);

    expect($result->stored)->toBeTrue();

    $row = $container->make(ApplicationRepository::class)->find((int) $result->id);
    expect($row)->not->toBeNull()
        ->and($row['status'])->toBe('new')
        ->and($row['name'])->toBe('Applicant')
        ->and($row['job_id'])->toBe(0)
        // The whole point of #138 item 8: this column held `0` for every application ever
        // submitted, because no caller supplied the id the signature asked for.
        ->and((int) $row['cv_attachment'])->toBe(4242);

    $container->make(ApplicationRepository::class)->delete((int) $result->id);
    remove_all_filters('pre_wp_mail');
});
