<?php

/**
 * A form can ask for a file, and refuses one it should not keep (spec 081, #138 item 7).
 *
 * The ordering is the thing worth pinning: descriptors are validated **before** anything is stored,
 * so a refused submission leaves nothing on disk (FR-005). A store that ran first and cleaned up
 * afterwards would be correct only for as long as nobody added an early return between the two.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;
use Corex\Security\Upload\AttachmentResult;
use Corex\Security\Upload\AttachmentStorage;
use Corex\Support\BootLogger;

final class CvTestForm extends Form
{
    public string $slug = 'apply';

    /**
     * @var array<string,array{type?:string,rules?:list<string>,label?:string}>
     */
    protected array $fields = [
        'name' => ['type' => 'text', 'rules' => ['required']],
        'cv'   => ['type' => 'file', 'rules' => ['required', 'mime:application/pdf', 'max_size:2']],
    ];
}

/**
 * Records what it was asked to store, and can be told to refuse.
 */
final class SpyAttachmentStore implements AttachmentStorage
{
    /** @var list<string> */
    public array $storedContexts = [];

    /** @var list<int> */
    public array $forgotten = [];

    public function __construct(private readonly bool $accepts = true)
    {
    }

    public function store(array $file, string $context = ''): AttachmentResult
    {
        if (! $this->accepts) {
            return AttachmentResult::refused('move_failed');
        }

        $this->storedContexts[] = $context;

        return AttachmentResult::stored(900 + count($this->storedContexts));
    }

    public function forget(int $attachmentId): bool
    {
        $this->forgotten[] = $attachmentId;

        return true;
    }
}

function fileSubmissionService(AttachmentStorage $attachments): FormSubmissionService
{
    $forms = new FormRegistry();
    $forms->register(new CvTestForm());

    return new FormSubmissionService(
        $forms,
        new SchemaResolver(new RuleRegistry()),
        new Validator(new RuleRegistry()),
        new EventDispatcher(new ListenerProvider(), new BootLogger(false)),
        $attachments,
    );
}

/**
 * @return array{name:string,type:string,tmp_name:string,error:int,size:int}
 */
function pdfDescriptor(): array
{
    return [
        'name'     => 'cv.pdf',
        'type'     => 'application/pdf',
        'tmp_name' => '/tmp/php-upload-test',
        'error'    => UPLOAD_ERR_OK,
        'size'     => 100_000,
    ];
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    // The rules read the real file; these tests are about ordering, so the content check is
    // stubbed to agree and `MimeTypeRuleTest` covers what it does with real bytes.
    Functions\when('wp_check_filetype_and_ext')->justReturn([
        'ext'  => 'pdf',
        'type' => 'application/pdf',
        'proper_filename' => false,
    ]);
});

it('exchanges a validated descriptor for the attachment id it stored as', function () {
    $store = new SpyAttachmentStore();

    $response = fileSubmissionService($store)->handle(
        'apply',
        ['name' => 'Sam'],
        FormSubmissionService::HONEYPOT_KEY,
        ['cv' => pdfDescriptor()],
    );

    expect($response->isOk())->toBeTrue()
        ->and($response->value['cv'])->toBe(901)
        ->and($store->storedContexts)->toBe(['form-cv']);
});

/**
 * FR-005, and the reason storing happens after validation rather than before with a cleanup.
 */
it('stores nothing when another field fails validation', function () {
    $store = new SpyAttachmentStore();

    $response = fileSubmissionService($store)->handle(
        'apply',
        ['name' => ''],
        FormSubmissionService::HONEYPOT_KEY,
        ['cv' => pdfDescriptor()],
    );

    expect($response->isOk())->toBeFalse()
        ->and($store->storedContexts)->toBe([])
        ->and($store->forgotten)->toBe([]);
});

it('stores nothing when the file itself is refused', function () {
    Functions\when('wp_check_filetype_and_ext')->justReturn([
        'ext'  => false,
        'type' => false,
        'proper_filename' => false,
    ]);

    $store = new SpyAttachmentStore();

    $response = fileSubmissionService($store)->handle(
        'apply',
        ['name' => 'Sam'],
        FormSubmissionService::HONEYPOT_KEY,
        ['cv' => pdfDescriptor()],
    );

    expect($response->isOk())->toBeFalse()
        ->and($response->value['cv'] ?? null)->toBe('mime')
        ->and($store->storedContexts)->toBe([]);
});

/**
 * A missing file must reach `required` as missing, not as an empty descriptor — otherwise an
 * optional file field could never be left blank and a required one could never be enforced.
 */
it('treats an absent upload as absent', function () {
    $response = fileSubmissionService(new SpyAttachmentStore())->handle(
        'apply',
        ['name' => 'Sam'],
        FormSubmissionService::HONEYPOT_KEY,
        [],
    );

    expect($response->isOk())->toBeFalse()
        ->and($response->value['cv'] ?? null)->toBe('required');
});

it('refuses the submission when no store is configured, rather than losing the file', function () {
    $forms = new FormRegistry();
    $forms->register(new CvTestForm());

    $service = new FormSubmissionService(
        $forms,
        new SchemaResolver(new RuleRegistry()),
        new Validator(new RuleRegistry()),
        new EventDispatcher(new ListenerProvider(), new BootLogger(false)),
    );

    $response = $service->handle(
        'apply',
        ['name' => 'Sam'],
        FormSubmissionService::HONEYPOT_KEY,
        ['cv' => pdfDescriptor()],
    );

    // Accepting it would report success for a submission whose file went nowhere — which is
    // exactly the shape of the careers defect this spec exists to remove.
    expect($response->isOk())->toBeFalse();
});
