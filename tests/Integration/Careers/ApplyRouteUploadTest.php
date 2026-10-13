<?php

/**
 * What the careers apply route hands on as the CV, for the file the request carried.
 *
 * On a Windows host PHP names an uploaded file's temporary copy with backslashes
 * (`C:\Windows\Temp\php1A2B.tmp`). The route read `$_FILES['cv']` through `wp_unslash()`, which
 * removes backslashes from every string of the descriptor, so the path it handed on named no file
 * and every application on such a host was refused (measured over HTTP on 2026-10-13: 422,
 * `cv_move_failed`).
 *
 * The request is built as `WP_REST_Server::serve_request()` builds it, with `$_FILES` and the
 * request's file parameters holding the same descriptor, and goes through WordPress's REST server,
 * so the route is held whichever of the two it reads. The attachment store is substituted for the
 * reason `ApplicationDataPathTest` gives: `is_uploaded_file()` is false for any file a test
 * creates. It refuses what it is given, so no row is written and no email sent.
 *
 * @package Corex\Tests\Integration\Careers
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Captcha\Captcha;
use Corex\Captcha\NullCaptcha;
use Corex\Careers\Application\ApplicationService;
use Corex\Careers\Application\ApplicationStore;
use Corex\Mail\Mailer;
use Corex\Security\Upload\AttachmentResult;
use Corex\Security\Upload\AttachmentStorage;
use Corex\Security\Upload\UploadValidator;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    $container = Boot::app()->container();

    $this->storage = new class () implements AttachmentStorage {
        /** @var list<array<string,mixed>> */
        public array $handed = [];

        public function store(array $file, string $context = ''): AttachmentResult
        {
            $this->handed[] = $file;

            return AttachmentResult::refused('held_by_the_test');
        }

        public function forget(int $attachmentId): bool
        {
            return true;
        }
    };

    $this->service = $container->make(ApplicationService::class);
    $this->captcha = $container->make(Captcha::class);
    $this->files   = $_FILES;

    $container->instance(ApplicationService::class, new ApplicationService(
        $container->make(ApplicationStore::class),
        new UploadValidator(['application/pdf' => ['pdf']], 5 * 1024 * 1024),
        $this->storage,
        $container->make(Mailer::class),
        $container->make(ConfigInterface::class),
    ));
    // Whatever challenge the install is set to, this file is not about it.
    $container->instance(Captcha::class, new NullCaptcha());
});

afterEach(function () {
    $container = Boot::app()->container();
    $container->instance(ApplicationService::class, $this->service);
    $container->instance(Captcha::class, $this->captcha);

    $_FILES = $this->files;
});

/**
 * Posts an application to the route as a browser's multipart request arrives: `$_FILES` and the
 * request's file parameters hold the same thing.
 *
 * A PHP warning while the route answers fails the test: a list read as text is the word "Array"
 * and a warning in the log, with the same answer either way.
 *
 * @param array<string,mixed> $files what PHP made of the request's files
 */
function applyWithFiles(array $files): WP_REST_Response
{
    $_FILES = $files;

    $request = new WP_REST_Request('POST', '/corex/v1/careers/apply');
    $request->set_body_params(['job_id' => '7', 'name' => 'Salma Idris', 'email' => 'salma@example.com']);
    $request->set_file_params($_FILES);

    set_error_handler(static function (int $level, string $message): never {
        throw new ErrorException($message, 0, $level);
    }, E_WARNING);

    try {
        return rest_get_server()->dispatch($request);
    } finally {
        restore_error_handler();
    }
}

it('hands the store the temporary path PHP made, byte for byte', function (string $path) {
    applyWithFiles(['cv' => [
        'name'     => 'salma idris cv.pdf',
        'type'     => 'application/pdf',
        'tmp_name' => $path,
        'error'    => UPLOAD_ERR_OK,
        'size'     => 48_213,
    ]]);

    expect($this->storage->handed)->toHaveCount(1)
        ->and($this->storage->handed[0]['tmp_name'])->toBe($path)
        // The name the browser gave is still cleaned on the way.
        ->and($this->storage->handed[0]['name'])->toBe('salma-idris-cv.pdf');
})->with([
    'a Windows host' => ['C:\\Windows\\Temp\\php1A2B.tmp'],
    'a Linux host' => ['/tmp/phpQx7Lr2'],
]);

it('answers that no CV was uploaded, and hands the store nothing', function (array $files) {
    $response = applyWithFiles($files);

    expect($this->storage->handed)->toBe([])
        ->and($response->get_status())->toBe(422)
        ->and($response->get_data()['error'])->toBe('cv_upload_error');
})->with([
    'a request with no file' => [[]],
    // `name="cv[]"`: PHP puts a list in every slot of the descriptor.
    'several files under the one name' => [['cv' => [
        'name'     => ['one.pdf', 'two.pdf'],
        'type'     => ['application/pdf', 'application/pdf'],
        'tmp_name' => ['C:\\Windows\\Temp\\php1A2B.tmp', 'C:\\Windows\\Temp\\php3C4D.tmp'],
        'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        'size'     => [48_213, 51_877],
    ]]],
]);
