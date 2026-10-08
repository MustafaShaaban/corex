<?php

/**
 * Integration test: a form defined in code that asks to be protected, on real ./wp
 * (spec 104 US2; issue #264).
 *
 * The route is driven through the real middleware pipeline, sanitiser, validator and listeners.
 * Only the provider is stood in for: nothing here calls a challenge provider.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Captcha\CaptchaAssetController;
use Corex\Events\EventDispatcher;
use Corex\Forms\Block\ProtectedFormRegistry;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\SubmissionChallenge;
use Corex\Forms\Submission\SubmitController;
use Corex\Forms\Validation\Validator;
use Corex\Http\ClientAddress;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\Pipeline;
use Corex\Security\ChallengeVerifier;
use Corex\Security\Upload\AttachmentStorage;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\WatchedTransients;

const PROTECTED_PROBE = 'corex-protected-probe';

beforeEach(function () {
    $this->stored     = CreatedPosts::watch('corex_submission');
    $this->transients = WatchedTransients::watch('corex_throttle_');
    $this->settings   = [
        'corex_captcha_driver' => get_option('corex_captcha_driver', null),
        'corex_captcha_secret' => get_option('corex_captcha_secret', null),
        'corex_captcha_site_key' => get_option('corex_captcha_site_key', null),
    ];

    Boot::app()->container()->make(FormRegistry::class)->register(new class extends Form {
        public string $slug = PROTECTED_PROBE;

        protected array $fields = ['phone' => ['type' => 'text', 'rules' => ['required']]];

        public function protection(): array
        {
            return ['captcha' => 'on'];
        }

        public function listeners(): array
        {
            return [StoreSubmissionListener::class];
        }
    });
});

afterEach(function () {
    $this->stored->delete();
    $this->transients->restore();
    foreach ($this->settings as $option => $before) {
        $before === null ? delete_option($option) : update_option($option, $before);
    }
});

/** The route's controller as the container builds it, around a provider that accepts one token. */
function controllerWithProviderAccepting(string $accepted): SubmitController
{
    $container = Boot::app()->container();
    $provider  = new class($accepted) implements ChallengeVerifier {
        public function __construct(private string $accepted)
        {
        }

        public function verify(string $token): bool
        {
            return $token === $this->accepted;
        }
    };

    return new SubmitController(
        new FormSubmissionService(
            $container->make(FormRegistry::class),
            $container->make(SchemaResolver::class),
            $container->make(Validator::class),
            $container->make(EventDispatcher::class),
            $container->make(AttachmentStorage::class),
            new SubmissionChallenge($provider),
        ),
        $container->make(Pipeline::class),
        $container->make(MiddlewareResolver::class),
        $container->make(ClientAddress::class),
    );
}

/** @param array<string,mixed> $body */
function protectedProbeRequest(array $body): WP_REST_Request
{
    $request = new WP_REST_Request('POST', '/corex/v1/forms/' . PROTECTED_PROBE);
    $request->set_url_params(['slug' => PROTECTED_PROBE]);
    $request->set_body_params($body);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    return $request;
}

it('refuses a submission the provider does not accept, with a code of its own, and stores nothing', function () {
    $response = controllerWithProviderAccepting('good-token')
        ->submit(protectedProbeRequest(['phone' => '0100', 'captcha_token' => 'forged']));

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['ok'])->toBeFalse()
        ->and($response->get_data()['code'])->toBe('challenge_failed')
        ->and($response->get_data()['message'])->toBe('We could not verify your submission. Please try again.')
        ->and($this->stored->ids())->toBe([]);
});

it('stores a submission the provider accepts, without its token', function () {
    $response = controllerWithProviderAccepting('good-token')
        ->submit(protectedProbeRequest(['phone' => '0100', 'captcha_token' => 'good-token']));

    $storedId = $this->stored->ids()[0] ?? 0;

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['values'])->toBe(['phone' => '0100'])
        ->and($this->stored->ids())->toHaveCount(1)
        ->and(wp_json_encode(get_post_meta($storedId)))->not->toContain('good-token');
});

it('renders the token field through the block, and the page then loads the script of the provider', function () {
    update_option('corex_captcha_driver', 'recaptcha');
    update_option('corex_captcha_secret', 'a-secret');
    update_option('corex_captcha_site_key', 'a-site-key');

    $html = do_blocks('<!-- wp:corex/form {"formSlug":"' . PROTECTED_PROBE . '"} /-->');

    expect($html)->toContain('name="captcha_token"')
        ->and($html)->toContain('data-corex-captcha-action="corex_form_corex_protected_probe"')
        ->and(Boot::app()->container()->make(ProtectedFormRegistry::class)->all())
        ->toHaveKey(PROTECTED_PROBE);

    // What the captcha add-on does in the footer of a page that rendered a protected form.
    Boot::app()->container()->make(CaptchaAssetController::class)->enqueue();

    expect(wp_script_is('corex-recaptcha-v3-api', 'enqueued'))->toBeTrue()
        ->and(wp_script_is('corex-captcha-v3', 'enqueued'))->toBeTrue();

    wp_dequeue_script('corex-captcha-v3');
    wp_dequeue_script('corex-recaptcha-v3-api');
})->skip(
    fn (): bool => ! class_exists(CaptchaAssetController::class),
    'The captcha add-on is not active on this install.',
);
