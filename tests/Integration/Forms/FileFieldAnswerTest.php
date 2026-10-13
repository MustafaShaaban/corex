<?php

/**
 * Integration test: a file field's answer is the file the request carried, on real ./wp.
 *
 * Reported on 2026-10-13 from a client site. A form's route kept whatever the request's body said
 * under a file field's name when no file part arrived for it. An answer to a file question is read
 * everywhere as the number of a stored file: by the inbox, by the email that names it, and by
 * whatever removes a submission's files with the submission.
 *
 * Sent through the REST server, so the route, its sanitiser, the validator and the listener that
 * stores are the site's own. What cannot be sent from here is a file WordPress accepts as an
 * upload: `submission-file.spec.js` sends a real one, and a request like these, over HTTP.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Submissions\SubmissionQueryService;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Security\Upload\FormUploads;
use Corex\Security\Upload\ProtectedUploads;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\WatchedTransients;

const OPTIONAL_FILE_PROBE = 'corex-optional-file-probe';
const REQUIRED_FILE_PROBE = 'corex-required-file-probe';

beforeEach(function () {
    // Neither form sends an email. Said all the same: a development install may deliver for real.
    $this->mail       = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $this->posts      = CreatedPosts::watch('corex_submission', 'attachment');
    $this->transients = WatchedTransients::watch('corex_throttle_');

    $forms = Boot::app()->container()->make(FormRegistry::class);
    foreach ([OPTIONAL_FILE_PROBE => [], REQUIRED_FILE_PROBE => ['required']] as $slug => $rules) {
        $forms->register(new class ($slug, $rules) extends Form {
            /** @param list<string> $fileRules */
            public function __construct(string $slug, array $fileRules)
            {
                $this->slug   = $slug;
                $this->fields = [
                    'name' => ['type' => 'text', 'rules' => ['required']],
                    'cv' => ['type' => 'file', 'rules' => [...$fileRules, 'mime:application/pdf']],
                ];
            }

            public function listeners(): array
            {
                return [StoreSubmissionListener::class];
            }
        });
    }

    // A file somebody else sent with a form, through a field of the same name, as the store
    // leaves one: a private attachment in the protected directory, marked with its field.
    $this->heldPath = ProtectedUploads::ensure() . '/corex-held-' . wp_generate_password(8, false) . '.pdf';
    file_put_contents($this->heldPath, "%PDF-1.4\n%%EOF\n");
    $this->held = (int) wp_insert_attachment(
        ['post_title' => 'Somebody else’s CV', 'post_status' => 'private', 'post_mime_type' => 'application/pdf'],
        $this->heldPath,
    );
    update_post_meta($this->held, '_corex_protected', '1');
    update_post_meta($this->held, '_corex_upload_context', 'form-cv');

    $this->administrator = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
});

afterEach(function () {
    $this->posts->delete();
    $this->transients->restore();
    MailDelivery::stop($this->mail);
    wp_set_current_user(0);
});

/**
 * Send a form as a visitor does, with no file part.
 *
 * @param array<string,mixed> $answers
 */
function sendWithNoFile(string $form, array $answers, bool $asJson): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/corex/v1/forms/' . $form);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    if ($asJson) {
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode($answers));
    } else {
        $request->set_body_params($answers);
    }

    return rest_get_server()->dispatch($request);
}

/** @return list<int> The submissions stored since the test began. */
function submissionsStored(CreatedPosts $posts): array
{
    return array_values(array_filter(
        $posts->ids(),
        static fn (int $id): bool => get_post_type($id) === 'corex_submission',
    ));
}

/**
 * What a request can say under a file field's name, given a stored file's number and path, and
 * whether it says it as JSON.
 */
dataset('what the body says under the file field', [
    'its number as text' => [static fn (int $id, string $path): string => (string) $id, false],
    'its number in JSON' => [static fn (int $id, string $path): int => $id, true],
    'an array shaped like an upload, naming its path' => [
        static fn (int $id, string $path): array => [
            'name' => 'cv.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => 15,
        ],
        true,
    ],
    'the same array in a form body' => [
        static fn (int $id, string $path): array => [
            'name' => 'cv.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $path,
            'error' => '0',
            'size' => '15',
        ],
        false,
    ],
]);

it('stores no answer for a file field the request sent no file for', function (Closure $said, bool $asJson) {
    $response = sendWithNoFile(
        OPTIONAL_FILE_PROBE,
        ['name' => 'Sam', 'cv' => $said($this->held, $this->heldPath)],
        $asJson,
    );
    $stored = submissionsStored($this->posts);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['values'])->toBe(['name' => 'Sam'])
        ->and($stored)->toHaveCount(1)
        ->and(metadata_exists('post', $stored[0], 'corex_field_cv'))->toBeFalse()
        // Nothing was stored as a file either: the one attachment is the one that was there.
        ->and(array_values(array_diff($this->posts->ids(), $stored)))->toBe([$this->held])
        ->and(is_file($this->heldPath))->toBeTrue();
})->with('what the body says under the file field');

it('shows no file in the inbox for a submission that was sent none', function () {
    sendWithNoFile(OPTIONAL_FILE_PROBE, ['name' => 'Sam', 'cv' => (string) $this->held], false);
    $stored = submissionsStored($this->posts);

    wp_set_current_user($this->administrator);
    $inbox = Boot::app()->container()->make(SubmissionQueryService::class);

    expect($stored)->toHaveCount(1)
        ->and($inbox->file($this->administrator, $stored[0], 'cv'))->toBeNull();
});

it('keeps somebody else’s file when a submission that was sent none is deleted', function () {
    sendWithNoFile(OPTIONAL_FILE_PROBE, ['name' => 'Sam', 'cv' => (string) $this->held], false);
    $stored = submissionsStored($this->posts);

    expect($stored)->toHaveCount(1);
    wp_delete_post($stored[0], true);

    expect(get_post_type($this->held))->toBe('attachment')
        ->and(is_file($this->heldPath))->toBeTrue();

    // And what removes a submission's files is listening: the file goes with the submission that
    // does hold it.
    $holder = (int) wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'The submission the file was sent with',
        'meta_input' => ['corex_form_slug' => OPTIONAL_FILE_PROBE, 'corex_field_cv' => (string) $this->held],
    ]);
    wp_delete_post($holder, true);

    expect(get_post($this->held))->toBeNull()
        ->and(is_file($this->heldPath))->toBeFalse();
});

it('says a required file is missing, and stores nothing', function (Closure $said, bool $asJson) {
    $response = sendWithNoFile(
        REQUIRED_FILE_PROBE,
        ['name' => 'Sam', 'cv' => $said($this->held, $this->heldPath)],
        $asJson,
    );

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['errors'])->toBe(['cv' => 'required'])
        ->and(submissionsStored($this->posts))->toBe([]);
})->with('what the body says under the file field');

/**
 * What a site's own listener asks before it does something with a file it was handed the number of.
 */
it('says whether an attachment is a file sent through a form field of that name', function () {
    $logo = (int) wp_insert_attachment(['post_title' => 'Site logo', 'post_status' => 'inherit']);

    expect(FormUploads::cameThrough('cv', $this->held))->toBeTrue()
        ->and(FormUploads::cameThrough('brief', $this->held))->toBeFalse()
        ->and(FormUploads::cameThrough('cv', $logo))->toBeFalse()
        ->and(FormUploads::cameThrough('cv', 0))->toBeFalse();
});
