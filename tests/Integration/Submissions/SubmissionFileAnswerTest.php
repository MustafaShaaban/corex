<?php

/**
 * A file uploaded with a submission, read where the submission is read, in WordPress (spec 081,
 * FR-007 to FR-009; Phase F).
 *
 * Reported on 2026-10-11 from a client site: the inbox showed `224` under "Answers" for a
 * submission that carried an uploaded file. What only WordPress can show: that the number stored
 * on a real submission is found to be a real protected upload, that it is described the same in
 * the trash, and that the address it is opened at is the inbox's rule and not the attachment's.
 *
 * The bytes leaving are not asserted here: sending ends the request. The inbox's browser walk
 * opens the file and reads them.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Boot;
use Corex\Config\Submissions\SubmissionFileDelivery;
use Corex\Config\Submissions\SubmissionQueryService;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Security\Upload\ProtectedUploads;

const FILE_ANSWER_FORM = 'corex-file-answer-probe';

/** What the address answered when it did not send a file. */
final class FileAddressRefused extends RuntimeException
{
}

beforeEach(function () {
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }

    Boot::app()->container()->make(FormRegistry::class)->register(new class extends Form {
        public string $slug = FILE_ANSWER_FORM;

        protected array $fields = [
            'name' => ['type' => 'text'],
            'years' => ['type' => 'number'],
            'cv' => ['type' => 'file'],
        ];
    });

    $this->made    = [];
    $this->people  = [];
    $this->queries = Boot::app()->container()->make(SubmissionQueryService::class);
    $this->administrator = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($this->administrator);

    // `wp_die()` ends the request. Here it says what it would have answered.
    $this->refusal = static fn (): Closure => static function (string|WP_Error $message, string|int $title = '', array $args = []): never {
        throw new FileAddressRefused((string) $message, (int) ($args['response'] ?? 0));
    };
    add_filter('wp_die_handler', $this->refusal, PHP_INT_MAX);
});

afterEach(function () {
    remove_filter('wp_die_handler', $this->refusal, PHP_INT_MAX);
    remove_all_filters('corex_submission_manage_all');
    $_GET = [];

    foreach ($this->made as $id) {
        get_post_type($id) === 'attachment' ? wp_delete_attachment($id, true) : wp_delete_post($id, true);
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($this->people as $id) {
        wp_delete_user($id);
    }
    wp_set_current_user(0);
});

/**
 * A file as a form upload leaves it: a private attachment in the protected uploads directory,
 * marked with the field it came through.
 */
function fileSentThrough(string $field): int
{
    $path = ProtectedUploads::ensure() . '/corex-file-answer-' . wp_generate_password(8, false) . '.txt';
    file_put_contents($path, 'A curriculum vitae.');
    $attachment = (int) wp_insert_attachment(['post_title' => 'CV', 'post_status' => 'private', 'post_mime_type' => 'text/plain'], $path);
    update_post_meta($attachment, '_corex_protected', '1');
    update_post_meta($attachment, '_corex_upload_context', 'form-' . $field);

    return $attachment;
}

/** @param array<string,mixed> $meta */
function submissionWithAnswers(array $meta): int
{
    return (int) wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'File answer probe',
        'meta_input' => $meta + [
            'corex_form_slug' => FILE_ANSWER_FORM,
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'none',
            'corex_field_name' => 'Salma',
            'corex_submission_updated_at' => '2026-10-11T09:30:00+00:00',
        ],
    ]);
}

/** A signed-in person of one role, with the inbox when asked. */
function personWhoIs(string $role, bool $managesSubmissions): int
{
    $id = (int) wp_insert_user([
        'user_login' => 'corex-file-answer-' . strtolower(wp_generate_password(8, false)),
        'user_pass' => wp_generate_password(24),
        'role' => $role,
    ]);
    if ($managesSubmissions) {
        get_userdata($id)->add_cap(CorexAbility::MANAGE_SUBMISSIONS);
    }
    wp_set_current_user($id);

    return $id;
}

/**
 * What the address answers the person signed in, for a link made for them.
 *
 * @return array{0:int,1:string} The status and the sentence.
 */
function askForFile(int $submissionId, string $field, ?string $nonce = null): array
{
    parse_str((string) wp_parse_url(SubmissionFileDelivery::url($submissionId, $field), PHP_URL_QUERY), $_GET);
    if ($nonce !== null) {
        $_GET['_wpnonce'] = $nonce;
    }

    try {
        Boot::app()->container()->make(SubmissionFileDelivery::class)->serve();
    } catch (FileAddressRefused $refused) {
        return [$refused->getCode(), $refused->getMessage()];
    }

    return [200, ''];
}

it('describes a file uploaded with a submission by its name, with an address of the submission’s own', function () {
    $this->made[] = $cv = fileSentThrough('cv');
    $this->made[] = $id = submissionWithAnswers(['corex_field_cv' => (string) $cv]);

    $answer = $this->queries->detail($this->administrator, $id)['values']['cv'];
    parse_str((string) wp_parse_url($answer['url'], PHP_URL_QUERY), $address);

    expect($answer['id'])->toBe($cv)
        ->and($answer['name'])->toBe(basename((string) get_attached_file($cv)))
        ->and($answer['missing'])->toBeFalse()
        ->and($address['action'])->toBe(SubmissionFileDelivery::ACTION)
        ->and((int) $address['submission'])->toBe($id)
        ->and($address['field'])->toBe('cv');
});

it('describes the file of a submission in the trash, where it can still be read', function () {
    $this->made[] = $cv = fileSentThrough('cv');
    $this->made[] = $id = submissionWithAnswers(['corex_field_cv' => (string) $cv]);
    wp_update_post(['ID' => $id, 'post_status' => 'trash']);

    $detail = $this->queries->detail($this->administrator, $id);

    expect($detail['trashed'])->toBeTrue()
        ->and($detail['values']['cv']['name'])->toBe(basename((string) get_attached_file($cv)))
        ->and($detail['values']['cv']['url'])->toContain(SubmissionFileDelivery::ACTION);
});

it('says a file is missing when it has gone from disk, and gives no address for it', function () {
    $this->made[] = $cv = fileSentThrough('cv');
    $this->made[] = $id = submissionWithAnswers(['corex_field_cv' => (string) $cv]);
    wp_delete_file((string) get_attached_file($cv));

    $answer = $this->queries->detail($this->administrator, $id)['values']['cv'];

    expect($answer['missing'])->toBeTrue()
        ->and($answer['url'])->toBe('')
        ->and(askForFile($id, 'cv'))->toBe([404, 'That file is missing.']);
});

it('says a file is missing when its record is gone altogether', function () {
    $this->made[] = $id = submissionWithAnswers(['corex_field_cv' => '99999999']);

    expect($this->queries->detail($this->administrator, $id)['values']['cv'])
        ->toBe(['id' => 99999999, 'name' => '', 'missing' => true, 'url' => '']);
});

it('leaves out a file question nobody answered', function () {
    $this->made[] = $id = submissionWithAnswers([]);

    expect($this->queries->detail($this->administrator, $id)['values'])->toBe(['name' => 'Salma']);
});

it('does not take a number in another answer for a file, whatever file has that number', function () {
    $this->made[] = $cv = fileSentThrough('cv');
    $this->made[] = $id = submissionWithAnswers(['corex_field_years' => (string) $cv]);

    expect($this->queries->detail($this->administrator, $id)['values']['years'])->toBe((string) $cv)
        ->and(askForFile($id, 'years'))->toBe([404, 'That file is not available here.']);
});

it('refuses a link that was not made for this person, this submission and this field', function () {
    $this->made[] = $cv = fileSentThrough('cv');
    $this->made[] = $id = submissionWithAnswers(['corex_field_cv' => (string) $cv]);
    $this->made[] = $other = submissionWithAnswers(['corex_field_cv' => (string) $cv]);

    expect(askForFile($id, 'cv', 'not-a-nonce'))->toBe([403, 'That link is no longer valid.'])
        ->and(askForFile($id, 'cv', wp_create_nonce(SubmissionFileDelivery::ACTION . '_' . $other . '_cv')))
        ->toBe([403, 'That link is no longer valid.']);
});

it('tells somebody who is signed out to sign in', function () {
    $this->made[] = $cv = fileSentThrough('cv');
    $this->made[] = $id = submissionWithAnswers(['corex_field_cv' => (string) $cv]);
    wp_set_current_user(0);

    expect(askForFile($id, 'cv'))->toBe([401, 'Sign in to open this file.']);
});

it('refuses a signed-in person who does not manage submissions', function () {
    $this->made[] = $cv = fileSentThrough('cv');
    $this->made[] = $id = submissionWithAnswers(['corex_field_cv' => (string) $cv]);
    $this->people[] = personWhoIs('editor', managesSubmissions: false);

    // An editor may read a private attachment. The file is a submission's, and that is not enough.
    expect(current_user_can('read_post', $cv))->toBeTrue()
        ->and(askForFile($id, 'cv'))->toBe([403, 'You don’t have permission to open this file.']);
});

it('answers a person whose scope leaves the submission out as it answers for one that is not there', function () {
    $this->made[] = $cv = fileSentThrough('cv');
    $this->made[] = $id = submissionWithAnswers([
        'corex_field_cv' => (string) $cv,
        'corex_owner_type' => 'user',
        'corex_owner_key' => (string) $this->administrator,
    ]);
    $this->people[] = $person = personWhoIs('author', managesSubmissions: true);
    // This site lets a person manage only what is assigned to them.
    add_filter('corex_submission_manage_all', '__return_false');

    expect($this->queries->detail($person, $id))->toBeNull()
        ->and(askForFile($id, 'cv'))->toBe([404, 'That file is not available here.'])
        ->and(askForFile(99999999, 'cv'))->toBe([404, 'That file is not available here.']);
});

it('lets a person who was given the inbox open its files, though WordPress would not show them the attachment', function () {
    $this->made[] = $cv = fileSentThrough('cv');
    $this->made[] = $id = submissionWithAnswers(['corex_field_cv' => (string) $cv]);
    $this->people[] = $person = personWhoIs('author', managesSubmissions: true);

    // The rule the attachment's own address asks, which this person does not pass.
    expect(current_user_can('read_post', $cv))->toBeFalse()
        ->and($this->queries->file($person, $id, 'cv'))
        ->toBe(['id' => $cv, 'name' => basename((string) get_attached_file($cv)), 'missing' => false]);
});
