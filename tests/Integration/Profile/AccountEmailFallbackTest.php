<?php

/**
 * A designed account email is sent in WordPress's place only when it went (spec 120, FR-034):
 * delivered, accepted, queued, or kept on a development or staging site. One that failed or was
 * refused leaves WordPress to send its own, so the member is never left with no email; and the
 * reset link WordPress's own email carries then still works.
 *
 * Every email here is kept on the site: it answers as a development site.
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

use Corex\Mail\Capture\WpMailInterceptor;
use Corex\Mail\DesignedEmails;
use Corex\Mail\MailResult;
use Corex\Profile\Email\AccountEmails;
use Corex\Profile\Reset\ResetLink;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;

beforeEach(function () {
    if (! class_exists(AccountEmails::class)) {
        $this->markTestSkipped('The profile add-on is not active on this install.');
    }
    $this->container = Corex\Boot::app()->container();
    $this->container->make(WpMailInterceptor::class)->register();
    $this->posts = CreatedPosts::watch('corex_email_asset', 'corex_email_log');
    $this->settings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $login = 'fallback-' . strtolower(wp_generate_password(6, false, false));
    $this->login = $login;
    $this->userId = (int) wp_insert_user(['user_login' => $login, 'user_email' => $login . '@example.com', 'user_pass' => 'a-first-passphrase']);
    $this->pageId = (int) wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Account ' . $login, 'post_content' => '<!-- wp:corex/account /-->']);
    // What reached WordPress's own mail function: its email, sent where the designed one did not go.
    $this->wordpressSent = [];
    $this->spy = function (array $atts): array {
        $this->wordpressSent[] = $atts;

        return $atts;
    };
    add_filter('wp_mail', $this->spy);
    // A reset asked by somebody signed out names where it came from, as a request has.
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
});

afterEach(function () {
    remove_filter('wp_mail', $this->spy);
    unset($_SERVER['REMOTE_ADDR']);
    MailDelivery::stop($this->settings);
    wp_delete_post($this->pageId, true);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->userId);
    $this->posts->delete();
});

/** The account emails, with a designed send that ends the way it is told. */
function accountEmailsEnding(object $test, string $state): AccountEmails
{
    $designed = new class($state) implements DesignedEmails {
        public function __construct(private string $state)
        {
        }

        public function send(string $slug, array $to, array $context, string $path): ?MailResult
        {
            return new MailResult(
                attemptId: Corex\Support\Uuid::v4(),
                requestId: Corex\Support\Uuid::v4(),
                state: $this->state,
                provider: 'corex-mail',
                message: 'As the test says.',
                occurredAt: new DateTimeImmutable('now'),
                retryable: false,
            );
        }
    };

    return new AccountEmails($test->container->make(ResetLink::class), $designed);
}

dataset('designed send endings', [
    'failed' => [MailResult::STATE_FAILED, true],
    'refused' => [MailResult::STATE_REJECTED, true],
    'captured on a development site' => [MailResult::STATE_CAPTURED, false],
    'delivered' => [MailResult::STATE_SENT, false],
]);

it('FR-034: lets WordPress send its own reset only where the designed one did not go', function (string $state, bool $wordpressSends) {
    accountEmailsEnding($this, $state)->resetDuring($this->pageId, fn (): mixed => retrieve_password($this->login));

    expect(count($this->wordpressSent))->toBe($wordpressSends ? 1 : 0);
    if ($wordpressSends) {
        // The link in WordPress's own email still opens the reset: its key is the last one made.
        preg_match('/[?&]key=([^&\s]+)/', (string) $this->wordpressSent[0]['message'], $key);
        expect(check_password_reset_key($key[1] ?? '', $this->login))->toBeInstanceOf(WP_User::class);
    }
})->with('designed send endings');

it('FR-034: lets WordPress send its own new-member email only where the designed welcome did not go', function (string $state, bool $wordpressSends) {
    accountEmailsEnding($this, $state)->welcomeDuring($this->pageId, fn (): mixed => wp_new_user_notification($this->userId, null, 'user'));

    expect(count($this->wordpressSent))->toBe($wordpressSends ? 1 : 0);
})->with('designed send endings');
