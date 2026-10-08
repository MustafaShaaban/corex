<?php

/**
 * Integration test: a form defined in code with choices and an optional file, on real ./wp.
 *
 * Driven through the route's own controller, so the sanitiser, the validator and the listener are
 * the real ones. Three things a client site's contact form could not do, reported on 2026-10-08:
 * keep the boxes ticked in a checkbox group, refuse an answer the form never offered, and be sent
 * with its optional file left empty.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Forms\Submission\SubmitController;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\WatchedTransients;

const ENQUIRY_PROBE = 'corex-enquiry-probe';

beforeEach(function () {
    $this->stored     = CreatedPosts::watch('corex_submission');
    $this->transients = WatchedTransients::watch('corex_throttle_');

    Boot::app()->container()->make(FormRegistry::class)->register(new class extends Form {
        public string $slug = ENQUIRY_PROBE;

        protected array $fields = [
            'services' => [
                'type' => 'checkbox-group',
                'options' => ['design' => 'Design', 'print' => 'Print', 'web' => 'Web'],
            ],
            'reply_by' => [
                'type' => 'radio',
                'rules' => ['required'],
                'options' => ['email' => 'Email', 'phone' => 'Phone'],
            ],
            'brief' => ['type' => 'file', 'rules' => ['mime:application/pdf']],
        ];

        public function listeners(): array
        {
            return [StoreSubmissionListener::class];
        }
    });
});

afterEach(function () {
    $this->stored->delete();
    $this->transients->restore();
});

/**
 * @param array<string,mixed>                $answers
 * @param array<string,array<string,mixed>>  $files
 */
function submitEnquiry(array $answers, array $files = []): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/corex/v1/forms/' . ENQUIRY_PROBE);
    $request->set_url_params(['slug' => ENQUIRY_PROBE]);
    $request->set_body_params($answers);
    $request->set_file_params($files);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    return Boot::app()->container()->make(SubmitController::class)->submit($request);
}

it('keeps every box ticked in a checkbox group', function () {
    $response = submitEnquiry(['services' => ['design', 'web'], 'reply_by' => 'email']);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['values']['services'])->toBe(['design', 'web'])
        ->and($this->stored->ids())->toHaveCount(1);
});

it('refuses an answer the form never offered, and stores nothing', function (array $answers, string $field) {
    $response = submitEnquiry($answers);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['errors'])->toBe([$field => 'choice'])
        ->and($this->stored->ids())->toBe([]);
})->with([
    'a radio' => [['reply_by' => 'fax'], 'reply_by'],
    'one box among offered ones' => [['services' => ['design', 'fax'], 'reply_by' => 'email'], 'services'],
]);

/**
 * What a browser posting the form as multipart sends for a file input nobody touched.
 */
it('takes the form with its optional file left empty', function () {
    $response = submitEnquiry(
        ['reply_by' => 'phone'],
        ['brief' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]],
    );

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['values'])->toBe(['reply_by' => 'phone'])
        ->and($this->stored->ids())->toHaveCount(1);
});
