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
use Corex\Forms\Submission\FormSubmittedEvent;
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

final class EnquiryTestForm extends Form
{
    public string $slug = 'enquire';

    /**
     * @var array<string,array{type?:string,rules?:list<string>,label?:string}>
     */
    protected array $fields = [
        'name'  => ['type' => 'text', 'rules' => ['required']],
        'brief' => ['type' => 'file', 'rules' => ['mime:application/pdf']],
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
    $forms->register(new EnquiryTestForm());

    return new FormSubmissionService(
        $forms,
        new SchemaResolver(new RuleRegistry()),
        new Validator(new RuleRegistry()),
        new EventDispatcher(new ListenerProvider(), new BootLogger(false)),
        $attachments,
    );
}

/**
 * What PHP hands over for a file input the visitor left empty, in a form posted as multipart.
 *
 * @return array{name:string,type:string,tmp_name:string,error:int,size:int}
 */
function emptyFilePart(): array
{
    return ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
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

/**
 * A browser posting a form as multipart sends a part for every file input, chosen or not. The
 * empty one was passed on as an upload, got past `required` because it is not an empty value, and
 * then could not be stored: an optional file refused the whole submission with "The file could not
 * be stored.", and a required one said the same where it meant "required". Reported on 2026-10-08
 * from a client site.
 */
it('takes a submission whose optional file was left empty', function () {
    $store = new SpyAttachmentStore();

    $response = fileSubmissionService($store)->handle(
        'enquire',
        ['name' => 'Sam'],
        FormSubmissionService::HONEYPOT_KEY,
        ['brief' => emptyFilePart()],
    );

    expect($response->isOk())->toBeTrue()
        ->and($response->value)->toBe(['name' => 'Sam'])
        ->and($store->storedContexts)->toBe([]);
});

it('says a required file is missing when its part arrives empty', function () {
    $response = fileSubmissionService(new SpyAttachmentStore())->handle(
        'apply',
        ['name' => 'Sam'],
        FormSubmissionService::HONEYPOT_KEY,
        ['cv' => emptyFilePart()],
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

/**
 * What a request can put under a file field's name without sending a file: a number as text, the
 * same as a JSON number, and an array with the keys of an upload.
 *
 * @return array<string,array{0:mixed}>
 */
function answersThatAreNotAnUpload(): array
{
    return [
        'a number as text' => ['224'],
        'a number' => [224],
        'an array shaped like an upload' => [pdfDescriptor()],
    ];
}

/**
 * A file field's answer is the file the request carried in its file parts, or there is none.
 * Reported on 2026-10-13 from a client site: what the request's body said under the field's name
 * was kept as the answer, and reached the listeners as the number of a stored file.
 */
it('leaves out what the body says under an optional file field', function (mixed $typed) {
    $store = new SpyAttachmentStore();

    $response = fileSubmissionService($store)->handle(
        'enquire',
        ['name' => 'Sam', 'brief' => $typed],
        FormSubmissionService::HONEYPOT_KEY,
        [],
    );

    expect($response->isOk())->toBeTrue()
        ->and($response->value)->toBe(['name' => 'Sam'])
        ->and($store->storedContexts)->toBe([]);
})->with(answersThatAreNotAnUpload());

it('says a required file is missing whatever the body says under its name', function (mixed $typed, array $files) {
    $store = new SpyAttachmentStore();

    $response = fileSubmissionService($store)->handle(
        'apply',
        ['name' => 'Sam', 'cv' => $typed],
        FormSubmissionService::HONEYPOT_KEY,
        $files,
    );

    expect($response->isOk())->toBeFalse()
        ->and($response->value['cv'] ?? null)->toBe('required')
        ->and($store->storedContexts)->toBe([]);
})->with(answersThatAreNotAnUpload())->with([
    'and no file part' => [[]],
    'and an empty file part' => [['cv' => emptyFilePart()]],
]);

it('hands the listeners no answer for a file field the request sent no file for', function () {
    $forms = new FormRegistry();
    $forms->register(new EnquiryTestForm());
    $listeners = new ListenerProvider();
    $heard     = [];
    $listeners->listen(FormSubmittedEvent::class, static function (FormSubmittedEvent $event) use (&$heard): void {
        $heard[] = $event->values;
    });

    (new FormSubmissionService(
        $forms,
        new SchemaResolver(new RuleRegistry()),
        new Validator(new RuleRegistry()),
        new EventDispatcher($listeners, new BootLogger(false)),
        new SpyAttachmentStore(),
    ))->handle('enquire', ['name' => 'Sam', 'brief' => '224']);

    expect($heard)->toBe([['name' => 'Sam']]);
});

it('stores the file that was sent, whatever the body says under the same name', function () {
    $store = new SpyAttachmentStore();

    $response = fileSubmissionService($store)->handle(
        'apply',
        ['name' => 'Sam', 'cv' => '224'],
        FormSubmissionService::HONEYPOT_KEY,
        ['cv' => pdfDescriptor()],
    );

    expect($response->isOk())->toBeTrue()
        ->and($response->value['cv'])->toBe(901);
});
