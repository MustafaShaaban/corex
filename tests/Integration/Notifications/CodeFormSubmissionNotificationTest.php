<?php

/**
 * Integration test: a submission of a form defined in code reaches the notification centre, on
 * real ./wp.
 *
 * Reported on 2026-10-13 from a client site whose forms are all defined in code: dozens of
 * submissions stored, and no notification for any of them. The notification centre heard only of
 * a form built in Forms & Flows.
 *
 * Driven through the form route's own controller as a visitor, so the listeners, the event and
 * the producer are the site's own; read back as the people the notification is for.
 *
 * @package Corex\Tests\Integration\Notifications
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Boot;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Forms\Submission\SubmitController;
use Corex\Notifications\NotificationQuery;
use Corex\Notifications\NotificationService;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\NotificationRows;
use Corex\Tests\Support\WatchedTransients;

/** Every form this file registers has a slug starting with this, which is how its rows are found again. */
const NOTIFIED_PROBE = 'corex-notified-probe';

const NOTIFIED_PROBE_READER = 'corex-notified-probe-reader';

/** A form defined in code that says what it is called and lists its own listeners. */
function notifiedProbeForm(string $slug, array $listeners): Form
{
    return new class ($slug, $listeners) extends Form {
        protected array $fields = ['name' => ['type' => 'text', 'rules' => ['required']]];

        /** @param list<class-string> $listeners */
        public function __construct(string $slug, private readonly array $listeners)
        {
            $this->slug = $slug;
        }

        public function label(): string
        {
            return 'Showroom visit';
        }

        public function listeners(): array
        {
            return $this->listeners;
        }
    };
}

/** Send the form as a visitor does, signed in as nobody. */
function submitNotifiedProbe(string $slug): WP_REST_Response
{
    wp_set_current_user(0);

    $request = new WP_REST_Request('POST', '/corex/v1/forms/' . $slug);
    $request->set_url_params(['slug' => $slug]);
    $request->set_body_params(['name' => 'Salma']);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    return Boot::app()->container()->make(SubmitController::class)->submit($request);
}

/**
 * The notifications about this file's forms that the signed-in person is shown.
 *
 * @return list<array<string,mixed>>
 */
function notifiedProbeItemsShownTo(int $userId): array
{
    wp_set_current_user($userId);

    $shown = Boot::app()->container()->make(NotificationService::class)
        ->forCurrentActor(NotificationQuery::fromRequest([], 1, NotificationQuery::MAX_PER_PAGE));

    return array_values(array_filter(
        $shown['items'],
        static fn (array $item): bool => str_contains((string) $item['dedup_key'], NOTIFIED_PROBE),
    ));
}

/** A person with no role, so that what they hold is what the test gave them. */
function notifiedProbeReader(string $login, ?string $ability): int
{
    $leftover = get_user_by('login', $login);
    $userId   = $leftover instanceof WP_User ? (int) $leftover->ID : (int) wp_insert_user([
        'user_login' => $login,
        'user_pass'  => wp_generate_password(20, true, true),
        'user_email' => $login . '@example.com',
        'role'       => '',
    ]);

    if ($ability !== null) {
        (new WP_User($userId))->add_cap($ability);
    }

    return $userId;
}

beforeEach(function () {
    // One form here writes an email, and a development install may deliver for real. A form
    // defined in code sends through wp_mail() whatever the site decides for an email, so the
    // email is answered here, before WordPress hands it to anything: nothing leaves this file.
    $this->mailSettings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $this->mailAccepted = true;
    $this->answersMail  = fn (): bool => $this->mailAccepted;
    add_filter('pre_wp_mail', $this->answersMail);
    $this->stored       = CreatedPosts::watch('corex_submission');
    $this->transients   = WatchedTransients::watch('corex_throttle_');
    // A run that died before its afterEach leaves its rows, and the next would add to them.
    NotificationRows::forgetPrefixed('submission.new:' . NOTIFIED_PROBE);
    NotificationRows::forgetPrefixed('submission.email_failure:' . NOTIFIED_PROBE);

    $forms = Boot::app()->container()->make(FormRegistry::class);
    $forms->register(notifiedProbeForm(NOTIFIED_PROBE, [StoreSubmissionListener::class]));
    $forms->register(notifiedProbeForm(NOTIFIED_PROBE . '-unstored', []));
    $forms->register(notifiedProbeForm(
        NOTIFIED_PROBE . '-emailed',
        [StoreSubmissionListener::class, SendEmailListener::class],
    ));

    $this->manager  = notifiedProbeReader(NOTIFIED_PROBE_READER, CorexAbility::MANAGE_SUBMISSIONS);
    $this->stranger = notifiedProbeReader(NOTIFIED_PROBE_READER . '-without', null);
});

afterEach(function () {
    NotificationRows::forgetPrefixed('submission.new:' . NOTIFIED_PROBE);
    NotificationRows::forgetPrefixed('submission.email_failure:' . NOTIFIED_PROBE);
    $this->stored->delete();
    $this->transients->restore();
    remove_filter('pre_wp_mail', $this->answersMail);
    MailDelivery::stop($this->mailSettings);

    wp_set_current_user(0);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->manager);
    wp_delete_user($this->stranger);
});

it('tells whoever manages submissions of a stored one, named and linked to that form\'s rows', function () {
    $response = submitNotifiedProbe(NOTIFIED_PROBE);

    $shown = notifiedProbeItemsShownTo($this->manager);
    parse_str((string) wp_parse_url($shown[0]['action']['url'] ?? '', PHP_URL_QUERY), $asked);

    expect($response->get_status())->toBe(200)
        ->and($this->stored->ids())->toHaveCount(1)
        ->and($shown)->toHaveCount(1)
        ->and($shown[0]['type'])->toBe('submission.new')
        ->and($shown[0]['dedup_key'])->toBe('submission.new:' . NOTIFIED_PROBE)
        ->and($shown[0]['rendered']['title'])->toBe('New submission on “Showroom visit”')
        ->and($asked)->toBe(['page' => 'corex-submissions', 'corex_form' => 'slug:' . NOTIFIED_PROBE])
        ->and(notifiedProbeItemsShownTo($this->stranger))->toBe([]);
});

it('adds a second submission of the same form to the notification that is already there', function () {
    submitNotifiedProbe(NOTIFIED_PROBE);
    submitNotifiedProbe(NOTIFIED_PROBE);

    $shown = notifiedProbeItemsShownTo($this->manager);

    expect($this->stored->ids())->toHaveCount(2)
        ->and($shown)->toHaveCount(1)
        ->and($shown[0]['occurrences'])->toBe(2);
});

it('says nothing of a submission that was not stored', function () {
    $response = submitNotifiedProbe(NOTIFIED_PROBE . '-unstored');

    expect($response->get_status())->toBe(200)
        ->and($this->stored->ids())->toBe([])
        ->and(notifiedProbeItemsShownTo($this->manager))->toBe([]);
});

it('says so when the form\'s own notification email did not go, and only then', function (bool $accepted, array $told) {
    $this->mailAccepted = $accepted;

    submitNotifiedProbe(NOTIFIED_PROBE . '-emailed');

    $types = array_column(notifiedProbeItemsShownTo($this->manager), 'type');
    sort($types);

    expect($types)->toBe($told);
})->with([
    'an email WordPress took' => [true, ['submission.new']],
    'an email WordPress could not send' => [false, ['submission.email_failed', 'submission.new']],
]);
