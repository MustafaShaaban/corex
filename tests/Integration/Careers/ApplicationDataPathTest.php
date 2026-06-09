<?php

/**
 * Integration test: the application data path on real ./wp (spec 014 US2: FR-003, SC-002).
 * The provider creates the corex_applications table on init; this stores an application
 * through the real custom-table store and confirms the notification path. Mail is intercepted.
 *
 * @package Corex\Tests\Integration\Careers
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Careers\Application\ApplicationRepository;
use Corex\Careers\Application\ApplicationService;

it('stores a valid application through the real custom table', function () {
    add_filter('pre_wp_mail', '__return_true');

    $container = Boot::app()->container();
    $service   = $container->make(ApplicationService::class);

    $cv     = ['name' => 'cv.pdf', 'type' => 'application/pdf', 'size' => 100_000, 'error' => UPLOAD_ERR_OK];
    $result = $service->apply(0, ['name' => 'Applicant', 'email' => 'a-' . uniqid() . '@example.com'], $cv);

    expect($result->stored)->toBeTrue();

    $row = $container->make(ApplicationRepository::class)->find((int) $result->id);
    expect($row)->not->toBeNull()
        ->and($row['status'])->toBe('new')
        ->and($row['name'])->toBe('Applicant')
        ->and($row['job_id'])->toBe(0);

    $container->make(ApplicationRepository::class)->delete((int) $result->id);
    remove_all_filters('pre_wp_mail');
});
