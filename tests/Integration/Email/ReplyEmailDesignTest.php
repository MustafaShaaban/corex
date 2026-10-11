<?php

/**
 * A reply leaves the site as a designed email with a plain-text version (spec 106, slice 2).
 *
 * Through the reply route and the real container, on a site answered as a development one: the
 * email is read from where such a site keeps it, and reaches nobody.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Submissions\SubmissionsController;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Design\EmailPalette;
use Corex\Tests\Support\MailDelivery;

beforeEach(function () {
    $this->mailSettings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->recipient = 'salma.' . strtolower(wp_generate_password(10, false)) . '@example.com';
    $this->designSubmission = wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'A submission answered in the site design',
        'meta_input' => [
            'corex_form_slug' => 'contact',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'none',
            'corex_submitter_email' => $this->recipient,
        ],
    ]);
});

afterEach(function () {
    MailDelivery::stop($this->mailSettings);
    // Deleting the submission takes the email kept for it with it (spec 105).
    wp_delete_post($this->designSubmission, true);
});

it('sends the reply in the standard design, named for the site, with its words as plain text', function () {
    $request = new WP_REST_Request('POST', '/corex/v1/submissions/' . $this->designSubmission . '/reply');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_url_params(['id' => (string) $this->designSubmission]);
    $request->set_body_params([
        'subject' => 'Re: your message',
        'body' => "Hello Salma,\n\nSee <a href=\"https://example.com/offer\">the offer</a>.",
    ]);

    $answer = Boot::app()->container()->make(SubmissionsController::class)->reply($request)->get_data();
    $kept = array_values(array_filter(
        Boot::app()->container()->make(CapturedEmailRepository::class)->latest(20),
        fn ($capture): bool => $capture->to === [$this->recipient],
    ));
    $email = $kept[0] ?? null;
    $site = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);

    expect($answer['data']['result']['state'] ?? $answer)->toBe('captured')
        ->and($kept)->toHaveCount(1)
        ->and($email->subject)->toBe('Re: your message')
        ->and($email->body)->toStartWith('<!DOCTYPE html>')
        ->and($email->body)->toContain('dir="' . (is_rtl() ? 'rtl' : 'ltr') . '"')
        ->and($email->body)->toContain(htmlspecialchars($site, ENT_QUOTES))
        ->and($email->body)->toContain('border-top:4px solid ' . EmailPalette::light()->accent)
        ->and(substr_count($email->body, 'Hello Salma,'))->toBe(1)
        ->and($email->body)->toContain('href="https://example.com/offer">the offer</a>')
        ->and($email->plainText)->toStartWith("Hello Salma,\n\nSee the offer (https://example.com/offer).\n\n-- \n")
        ->and($email->plainText)->toContain($site)
        ->and($email->plainText)->not->toContain('<');
});

/** A site's own design, as its plugin would hand one to the filter. */
final class AcmeSiteEmailDesign implements \Corex\Email\Design\EmailDesign
{
    public function __construct(private readonly bool $keepsTheMessage = true)
    {
    }

    public function render(\Corex\Email\Design\EmailContent $content, \Corex\Email\Design\EmailBrand $brand): string
    {
        return '<!DOCTYPE html><html><body><h1 class="acme">' . esc_html($brand->name) . '</h1>'
            . ($this->keepsTheMessage ? $content->bodyHtml : '<p>Thank you for your message.</p>')
            . '</body></html>';
    }
}

/** Sends a reply to the test's submission and answers the email a development site kept for it. */
function keptReplyFor(object $test, string $body): object
{
    $request = new WP_REST_Request('POST', '/corex/v1/submissions/' . $test->designSubmission . '/reply');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_url_params(['id' => (string) $test->designSubmission]);
    $request->set_body_params(['subject' => 'Re: your message', 'body' => $body]);
    Boot::app()->container()->make(SubmissionsController::class)->reply($request);

    return array_values(array_filter(
        Boot::app()->container()->make(CapturedEmailRepository::class)->latest(20),
        static fn ($capture): bool => $capture->to === [$test->recipient],
    ))[0];
}

// Spec 106, FR-014: from the site's own code, through a filter, with no framework file changed.
it('sends the reply in the design a site gives', function () {
    $own = static fn (): AcmeSiteEmailDesign => new AcmeSiteEmailDesign();
    add_filter('corex_email_design', $own);

    $email = keptReplyFor($this, "Hello Salma,\n\nWe will call you tomorrow.");
    remove_filter('corex_email_design', $own);

    expect($email->body)->toContain('<h1 class="acme">')
        ->and($email->body)->toContain('<p>We will call you tomorrow.</p>')
        ->and($email->body)->not->toContain('border-top:4px solid')
        ->and($email->plainText)->toStartWith("Hello Salma,\n\nWe will call you tomorrow.");
});

// FR-017: a reply is never sent without its words, and a broken template never stops one.
it('sends the reply in the standard design when the site design leaves the message out, and announces it', function () {
    $announced = [];
    Boot::app()->container()->make(\Corex\Events\ListenerProvider::class)->listen(
        \Corex\Email\Design\EmailDesignFailedEvent::class,
        static function (object $event) use (&$announced): void {
            $announced[] = [$event->design, $event->reason];
        },
    );
    $broken = static fn (): AcmeSiteEmailDesign => new AcmeSiteEmailDesign(keepsTheMessage: false);
    add_filter('corex_email_design', $broken);

    $email = keptReplyFor($this, 'We will call you tomorrow.');
    remove_filter('corex_email_design', $broken);
    // The notification its managers get is the producer's own test; here, that it is listening.
    Boot::app()->container()->make(\Corex\Notifications\NotificationService::class)
        ->resolve('email.design_failed:AcmeSiteEmailDesign', 'The test that raised it is over.');

    expect($email->body)->toContain('<p dir="auto"')
        ->and($email->body)->toContain('We will call you tomorrow.')
        ->and($email->body)->toContain('border-top:4px solid ' . EmailPalette::light()->accent)
        ->and($email->body)->not->toContain('class="acme"')
        ->and($announced)->toBe([['AcmeSiteEmailDesign', 'without_message']])
        ->and(Boot::app()->container()->make(\Corex\Notifications\NotificationProducerRegistry::class)->registeredKeys())
        ->toContain('email.design_failures');
});
