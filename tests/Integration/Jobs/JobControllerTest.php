<?php

/**
 * Integration tests for bounded-job status, cancellation, retry, and REST routes (spec 068).
 *
 * @package Corex\Tests\Integration\Jobs
 */

declare(strict_types=1);

use Corex\Config\Jobs\JobController;
use Corex\Config\Jobs\JobTable;
use Corex\Config\Jobs\WpJobRepository;
use Corex\Database\Schema\Migrator;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobDispatcher;
use Corex\Jobs\JobService;

const JOB_TEST_HASHES = [
    'status' => 'bce9a96092184d7764b00d2c846b51173fbfa640e68b9e51ea7e99a7349c6b79',
    'retry'  => '8e16e8641688b36b61c053804bfa41152a1f9460235f850c585983a983fff099',
];

beforeEach(function () {
    global $wpdb;

    $this->migrator = new Migrator();
    $this->migrator->create((new JobTable())->schema());
    $this->repository = new WpJobRepository($this->migrator);
    $this->dispatcher = new class implements JobDispatcher {
        /** @var list<int> */
        public array $dispatched = [];
        /** @var list<int> */
        public array $cancelled = [];

        public function available(): bool
        {
            return true;
        }

        public function dispatch(BoundedJob $job): void
        {
            $this->dispatched[] = $job->id;
        }

        public function cancel(int $jobId): void
        {
            $this->cancelled[] = $jobId;
        }
    };
    $this->service    = new JobService($this->repository, $this->dispatcher);
    $this->controller = new JobController($this->service);

    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));

    foreach (JOB_TEST_HASHES as $hash) {
        $wpdb->delete($this->migrator->fullName(JobTable::NAME), ['input_hash' => $hash]);
    }
});
afterEach(function () {
    global $wpdb;

    foreach (JOB_TEST_HASHES as $hash) {
        $wpdb->delete($this->migrator->fullName(JobTable::NAME), ['input_hash' => $hash]);
    }
});

it('registers status cancel and retry routes', function () {
    add_action('rest_api_init', [$this->controller, 'register']);
    do_action('rest_api_init', rest_get_server());
    $routes = rest_get_server()->get_routes();

    expect($routes)->toHaveKey('/corex/v1/jobs/(?P<id>\d+)')
        ->and($routes)->toHaveKey('/corex/v1/jobs/(?P<id>\d+)/cancel')
        ->and($routes)->toHaveKey('/corex/v1/jobs/(?P<id>\d+)/retry');
});

it('returns persisted status and cancels active work', function () {
    $job = $this->service->enqueue('data.export', get_current_user_id(), 10, JOB_TEST_HASHES['status'], new DateTimeImmutable());
    $request = new WP_REST_Request('GET', '/corex/v1/jobs/' . $job->id);
    $request->set_param('id', $job->id);

    $status = $this->controller->show($request);
    $cancel = $this->controller->cancel($request);

    expect($status->get_data()['data']['job']['state'])->toBe(BoundedJob::STATE_QUEUED)
        ->and($cancel->get_data()['data']['job']['state'])->toBe(BoundedJob::STATE_CANCELLED)
        ->and($this->dispatcher->cancelled)->toBe([$job->id]);
});

it('retries a failed job and rejects retry for an active job', function () {
    $now = new DateTimeImmutable();
    $job = $this->service->enqueue('models.import', get_current_user_id(), 10, JOB_TEST_HASHES['retry'], $now);
    $failed = $job->start($now)->fail('Temporary source failure.', $now->modify('+1 second'));
    $this->repository->save($failed);

    $request = new WP_REST_Request('POST', '/corex/v1/jobs/' . $job->id . '/retry');
    $request->set_param('id', $job->id);
    $retried = $this->controller->retry($request);
    $again   = $this->controller->retry($request);

    expect($retried->get_status())->toBe(200)
        ->and($retried->get_data()['data']['job']['state'])->toBe(BoundedJob::STATE_QUEUED)
        ->and($again->get_status())->toBe(409)
        ->and($again->get_data()['code'])->toBe('job_not_retryable');
});

it('requires the declared operations ability and a REST nonce for mutation', function () {
    $request = new WP_REST_Request('POST', '/corex/v1/jobs/1/cancel');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    expect($this->controller->canManage())->toBeTrue()
        ->and($this->controller->canMutate($request))->toBeTrue();

    $request->set_header('X-WP-Nonce', 'invalid');
    expect($this->controller->canMutate($request))->toBeFalse();
});
