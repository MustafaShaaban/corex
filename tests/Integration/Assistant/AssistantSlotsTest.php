<?php

/**
 * Integration test: a requested time is asked for once, on real ./wp (spec 129, slice 3, FR-021, FR-022).
 *
 * Driven through the forms route's own controller and WordPress's REST server, so the sanitiser, the
 * validator, the `assistant_slot` rule, the form's `prepare()`, the store and the claim's last listener are
 * the real ones, and the claims are rows of the real options table. A request for a time is stored with the
 * team's line written by the server; the slots route stops offering it; a second request for it is refused,
 * whether it arrives after the first or slips past the rule while the first is being taken; binning,
 * restoring and deleting the request in the inbox's own trash store frees, takes and frees the time again.
 *
 * The booking hours are answered through their `pre_option_` filter, and the callback's listeners are cut to
 * storage alone, so no setting of the site is touched and no email is sent. The site's own claim rows are
 * set aside before each test and put back after it. Each test asks for the second time on offer: the first
 * is the coming hour, which could pass between reading the list and sending.
 *
 * @package Corex\Tests\Integration\Assistant
 */

declare(strict_types=1);

use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Booking\SlotClaims;
use Corex\Assistant\Forms\AssistantCallbackForm;
use Corex\Boot;
use Corex\Config\Submissions\WpSubmissionTrashStore;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Forms\Submission\SubmitController;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\NotificationRows;
use Corex\Tests\Support\WatchedTransients;

/** @return list<string> the claim rows, by name */
function assistantSlotRows(): array
{
    global $wpdb;

    return $wpdb->get_col($wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name",
        $wpdb->esc_like('corex_assistant_slot_') . '%',
    ));
}

/** What a time's claim row holds, read from the table: the code never reads these rows through the option cache. */
function assistantSlotHolder(int $slot): ?string
{
    global $wpdb;
    $held = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'corex_assistant_slot_' . $slot));

    return $held === null ? null : (string) $held;
}

/** Removes every claim row, and answers what they held, so the site's own can be put back. */
function assistantSlotTakeRows(): array
{
    global $wpdb;
    $like = $wpdb->esc_like('corex_assistant_slot_') . '%';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $like), ARRAY_A);
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));

    return is_array($rows) ? $rows : [];
}

/** @param list<array{option_name: string, option_value: string}> $rows */
function assistantSlotPutBack(array $rows): void
{
    global $wpdb;
    assistantSlotTakeRows();
    foreach ($rows as $row) {
        $wpdb->insert($wpdb->options, $row + ['autoload' => 'off']);
    }
}

/** The second time the slots route offers. */
function assistantNextSlot(): int
{
    return assistantOffered()[1];
}

/** The team's line for a moment, as the site writes it in its own language. */
function assistantTeamLine(int $slot): string
{
    $format = 'D j M, ' . (get_option('time_format') ?: 'g:i a');

    return wp_date($format, $slot, new DateTimeZone('Asia/Riyadh')) . ' Riyadh = ' . wp_date($format, $slot, new DateTimeZone('Africa/Cairo')) . ' Cairo';
}

/** @param array<string, mixed> $answers */
function assistantCallback(array $answers): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/corex/v1/forms/' . AssistantCallbackForm::SLUG);
    $request->set_url_params(['slug' => AssistantCallbackForm::SLUG]);
    $request->set_body_params($answers + ['name' => 'Sara', 'channel' => 'call', 'phone' => '+20 100 123 4567', 'zone' => 'Asia/Riyadh']);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    return Boot::app()->container()->make(SubmitController::class)->submit($request);
}

/** @return list<int> the times the slots route offers */
function assistantOffered(): array
{
    return rest_do_request(new WP_REST_Request('GET', '/corex/v1/assistant/slots'))->get_data()['slots'];
}

beforeEach(function () {
    if (! isset(rest_get_server()->get_routes()['/corex/v1/assistant/slots'])) {
        $this->markTestSkipped('The corex-assistant add-on is not active on this install.');
    }
    if (Boot::app()->container()->make(\Corex\Forms\FormRegistry::class)->find(AssistantCallbackForm::SLUG) === null) {
        $this->markTestSkipped('corex-forms is not active on this install.');
    }

    $this->stored = CreatedPosts::watch('corex_submission');
    $this->transients = WatchedTransients::watch('corex_throttle_');
    $this->submissionNotifications = NotificationRows::snapshotPrefixed('submission.');
    $this->siteClaims = assistantSlotTakeRows();

    // Every day, round the clock, hourly, in Cairo, for two days: the next time is inside the coming hour.
    $this->booking = static fn (): array => ['enabled' => true, 'timezone' => 'Africa/Cairo', 'days' => [0, 1, 2, 3, 4, 5, 6], 'start' => '00:00', 'end' => '00:00', 'slot' => 60, 'notice' => 0, 'horizon' => 2];
    add_filter('pre_option_' . AssistantSettings::BOOKING_OPTION, $this->booking);
    $this->storeOnly = static fn (): array => [StoreSubmissionListener::class];
    add_filter('corex_assistant_callback_listeners', $this->storeOnly);
    wp_set_current_user(0);
});

afterEach(function () {
    if (! isset($this->stored)) {
        return;
    }
    remove_filter('pre_option_' . AssistantSettings::BOOKING_OPTION, $this->booking);
    remove_filter('corex_assistant_callback_listeners', $this->storeOnly);
    $this->stored->delete();
    assistantSlotPutBack($this->siteClaims);
    NotificationRows::restorePrefixed('submission.', $this->submissionNotifications);
    $this->transients->restore();
});

it('gives each time to one claim in the real options table, and frees it by its submission', function () {
    $claims = Boot::app()->container()->make(SlotClaims::class);
    $soon = time() + 7200;
    $past = time() - 7200;

    $first = $claims->claim($soon);
    $second = $claims->claim($soon);
    $claims->confirm($soon, 512);
    $claims->claim($past);
    $claims->forgetBefore(time());
    $kept = $claims->claimed();
    $claims->release($soon);
    $afterRelease = $claims->claimed();
    $claims->releaseFor(512);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($kept)->toBe([$soon])
        // A claim that names its submission is not a pending one: only its submission frees it.
        ->and($afterRelease)->toBe([$soon])
        ->and($claims->claimed())->toBe([])
        ->and(wp_load_alloptions())->not->toHaveKey('corex_assistant_slot_' . $soon);
});

it('frees a pending claim whose request died, and lets the next claim take it over', function () {
    global $wpdb;
    $claims = Boot::app()->container()->make(SlotClaims::class);
    $died = time() + 7200;
    $running = time() + 10800;
    $wpdb->insert($wpdb->options, ['option_name' => 'corex_assistant_slot_' . $died, 'option_value' => 'pending:' . (time() - 3600), 'autoload' => 'off']);
    $wpdb->insert($wpdb->options, ['option_name' => 'corex_assistant_slot_' . $running, 'option_value' => 'pending:' . (time() - 60), 'autoload' => 'off']);

    $offered = $claims->claimed();
    $tookDied = $claims->claim($died);
    $tookRunning = $claims->claim($running);
    $claims->confirm($died, 77);

    expect($offered)->toBe([$running])
        ->and($tookDied)->toBeTrue()
        ->and($tookRunning)->toBeFalse()
        ->and(assistantSlotHolder($died))->toBe('77')
        // Another request's pending claim is not this one's to name.
        ->and(assistantSlotHolder($running))->toStartWith('pending:');
});

it('stores a request with the team’s line written by the server, then stops offering its time', function () {
    $slot = assistantNextSlot();

    $response = assistantCallback(['slot' => (string) $slot, 'time' => 'Whatever the visitor typed']);
    $id = $this->stored->ids()[0] ?? 0;

    expect($response->get_status())->toBe(200)
        ->and(get_post_meta($id, 'corex_field_time', true))->toBe(assistantTeamLine($slot))
        ->and(get_post_meta($id, 'corex_field_slot', true))->toBe(gmdate('Y-m-d\TH:i:s\Z', $slot))
        ->and(assistantSlotRows())->toBe(['corex_assistant_slot_' . $slot])
        ->and(assistantSlotHolder($slot))->toBe((string) $id)
        ->and(assistantOffered())->not->toContain($slot);
});

it('refuses a second request for a time with "slot_taken", and stores nothing for it', function () {
    $slot = assistantNextSlot();
    assistantCallback(['slot' => (string) $slot]);

    $again = assistantCallback(['slot' => (string) $slot, 'name' => 'Omar']);

    expect($again->get_status())->toBe(422)
        ->and($again->get_data()['errors'])->toBe(['slot' => 'slot_taken'])
        ->and($this->stored->ids())->toHaveCount(1);
});

it('refuses the request whose claim loses the race in prepare(), and leaves the winner’s claim', function () {
    global $wpdb;
    $slot = assistantNextSlot();
    // Another request writes its claim at the moment this one's own claim goes to the database: after this
    // request's rule has read the claims and passed, whatever order it read them in.
    $raced = false;
    $racer = static function (string $query) use ($slot, &$raced): string {
        if (! $raced && str_starts_with($query, 'INSERT IGNORE') && str_contains($query, "'corex_assistant_slot_" . $slot . "'")) {
            $raced = true;
            global $wpdb;
            $wpdb->insert($wpdb->options, ['option_name' => 'corex_assistant_slot_' . $slot, 'option_value' => '999999', 'autoload' => 'off']);
        }

        return $query;
    };
    add_filter('query', $racer);

    $response = assistantCallback(['slot' => (string) $slot]);
    remove_filter('query', $racer);

    expect($raced)->toBeTrue()
        ->and($response->get_status())->toBe(422)
        ->and($response->get_data()['errors'])->toBe(['slot' => 'slot_taken'])
        ->and($this->stored->ids())->toBe([])
        ->and(assistantSlotHolder($slot))->toBe('999999');
});

it('offers the time again when its request is binned or deleted, and takes it again on restore', function () {
    $slot = assistantNextSlot();
    assistantCallback(['slot' => (string) $slot]);
    $id = $this->stored->ids()[0];
    $trash = Boot::app()->container()->make(WpSubmissionTrashStore::class);

    $trash->trash($id, 1, WpSubmissionTrashStore::VIA_INBOX);
    $binned = assistantOffered();
    $trash->restore($id);
    $restored = assistantOffered();
    $trash->trash($id, 1, WpSubmissionTrashStore::VIA_INBOX);
    $trash->restore($id);
    $trash->trash($id, 1, WpSubmissionTrashStore::VIA_INBOX);
    $trash->delete($id);

    expect($binned)->toContain($slot)
        ->and($restored)->not->toContain($slot)
        ->and(assistantOffered())->toContain($slot)
        ->and(assistantSlotRows())->toBe([]);
});

it('takes no time for "any time", and keeps what the visitor typed', function () {
    $response = assistantCallback(['slot' => '', 'time' => 'after work']);
    $id = $this->stored->ids()[0] ?? 0;

    expect($response->get_status())->toBe(200)
        ->and(get_post_meta($id, 'corex_field_time', true))->toBe('after work')
        ->and(assistantSlotRows())->toBe([]);
});

it('hides no time when nothing stores the request', function () {
    $slot = assistantNextSlot();
    $nothing = static fn (): array => [];
    add_filter('corex_assistant_callback_listeners', $nothing, 20);

    $response = assistantCallback(['slot' => (string) $slot]);
    remove_filter('corex_assistant_callback_listeners', $nothing, 20);

    expect($response->get_status())->toBe(200)
        ->and($this->stored->ids())->toBe([])
        ->and(assistantSlotRows())->toBe([])
        ->and(assistantOffered())->toContain($slot);
});
