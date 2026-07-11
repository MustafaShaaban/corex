<?php

/**
 * Performance contracts for admin reads and front-end form acceptance (spec 068 T230).
 *
 * The reference goals (plan.md): interactive admin reads return within one second at p95 for a
 * 10,000-record source and never render more than 100 rows; front-end form acceptance completes
 * within two seconds excluding external-provider latency. These contracts assert both the hard
 * boundedness guarantee and a generous wall-clock ceiling on the real code paths.
 *
 * @package Corex\Tests\Performance
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\QueryableDataSource;
use Corex\Forms\Submission\SubmitController;

const PERFORMANCE_SOURCE_RECORDS = 10000;
const PERFORMANCE_ADMIN_READ_BUDGET_MS = 1000.0;
const PERFORMANCE_FORM_ACCEPT_BUDGET_MS = 2000.0;

/** @return list<float> ascending timings in milliseconds */
function percentile(array $timings, float $percentile): float
{
    sort($timings);
    $index = (int) ceil(($percentile / 100) * count($timings)) - 1;

    return $timings[max(0, min(count($timings) - 1, $index))];
}

function tenThousandRecordSource(): DataSource
{
    $records = [];
    for ($id = 1; $id <= PERFORMANCE_SOURCE_RECORDS; $id++) {
        $records[$id] = ['id' => $id, 'name' => 'Contact ' . $id, 'status' => $id % 2 === 0 ? 'active' : 'inactive'];
    }

    return new class($records) implements DataSource, QueryableDataSource {
        /** @param array<int,array<string,mixed>> $records */
        public function __construct(private array $records) {}
        public function key(): string { return 'perf-contacts'; }
        public function label(): string { return 'Performance Contacts'; }
        public function columns(): array { return [['id' => 'name', 'label' => 'Name'], ['id' => 'status', 'label' => 'Status']]; }
        public function rows(int $page, int $perPage): array { return array_slice(array_values($this->records), ($page - 1) * $perPage, $perPage); }
        public function total(): int { return count($this->records); }
        public function delete(int $id): bool { return false; }
        public function query(DataQuery $query): array { return $this->rows($query->page, $query->perPage); }
        public function count(DataQuery $query): int { return $this->total(); }
        public function record(int $id): ?array { return $this->records[$id] ?? null; }
    };
}

it('bounds a 10,000-record admin read to at most 100 rows even when more are requested', function () {
    $source = tenThousandRecordSource();

    // Even an oversized client request is clamped to the 100-row page ceiling.
    $query = DataQuery::from(['per_page' => 10000, 'page' => 1]);
    $rows = $source->query($query);

    expect($source->total())->toBe(PERFORMANCE_SOURCE_RECORDS)
        ->and($query->perPage)->toBe(DataQuery::MAX_PER_PAGE)
        ->and(count($rows))->toBeLessThanOrEqual(100)
        ->and(count($rows))->toBe(100);
});

it('serves a 10,000-record admin read within the one-second p95 budget', function () {
    $source = tenThousandRecordSource();
    $query = DataQuery::from(['per_page' => 100, 'page' => 1]);

    $timings = [];
    for ($i = 0; $i < 30; $i++) {
        $start = hrtime(true);
        $rows = $source->query($query);
        $total = $source->count($query);
        $timings[] = (hrtime(true) - $start) / 1e6;

        expect(count($rows))->toBe(100)->and($total)->toBe(PERFORMANCE_SOURCE_RECORDS);
    }

    expect(percentile($timings, 95))->toBeLessThan(PERFORMANCE_ADMIN_READ_BUDGET_MS);
});

it('accepts a valid front-end form submission within the two-second budget', function () {
    add_filter('pre_wp_mail', '__return_true'); // exclude external mail-provider latency

    $baseline = get_posts(['post_type' => 'corex_submission', 'post_status' => 'any', 'posts_per_page' => 500, 'fields' => 'ids']);

    $controller = Boot::app()->container()->make(SubmitController::class);
    $request = new WP_REST_Request('POST', '/corex/v1/forms/contact');
    $request->set_url_params(['slug' => 'contact']);
    $request->set_body_params(['name' => 'Perf Tester', 'email' => 'perf@example.com', 'message' => 'A valid performance submission.']);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    $start = hrtime(true);
    $response = $controller->submit($request);
    $elapsedMs = (hrtime(true) - $start) / 1e6;

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['ok'])->toBeTrue()
        ->and($elapsedMs)->toBeLessThan(PERFORMANCE_FORM_ACCEPT_BUDGET_MS);

    // Clean up the stored submission this contract created.
    $after = get_posts(['post_type' => 'corex_submission', 'post_status' => 'any', 'posts_per_page' => 500, 'fields' => 'ids']);
    foreach (array_diff($after, $baseline) as $id) {
        wp_delete_post((int) $id, true);
    }
    remove_all_filters('pre_wp_mail');
});
