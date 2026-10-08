<?php

/**
 * Unit tests for the form block renderer (spec US4: FR-013, FR-015, SC-005, SC-007).
 *
 * Accessible, token-only, i18n markup: every field has an associated label, required
 * markers, a nonce carrier, and the honeypot — with no hardcoded colors or sizes.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Block\FieldRenderer;
use Corex\Forms\Block\FormBlockRenderer;
use Corex\Forms\Block\ProtectedFormRegistry;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Forms\ContactForm;
use Corex\Forms\Schema\SchemaExporter;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Submission\FormChallengeContextFactory;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Http\RemoteAddress;
use Corex\Support\Config\ConfigInterface;

/** @param array<string,mixed> $values */
function siteSettings(array $values): ConfigInterface
{
    return new class($values) implements ConfigInterface {
        /** @param array<string,mixed> $values */
        public function __construct(private array $values)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->values);
        }
    };
}

function renderContactForm(array $attributes): string
{
    return renderRegisteredForm(new ContactForm(), $attributes);
}

/** A form that states its own wording, and nothing else of its own. */
function formThatReads(string $submitLabel, string $success = '', string $error = ''): Form
{
    return new class($submitLabel, $success, $error) extends Form {
        public string $slug = 'callback';

        protected array $fields = ['phone' => ['type' => 'text', 'rules' => ['required']]];

        public function __construct(private string $submit, private string $success, private string $error)
        {
        }

        public function submitLabel(): string
        {
            return $this->submit;
        }

        public function successMessage(): string
        {
            return $this->success;
        }

        public function errorMessage(): string
        {
            return $this->error;
        }
    };
}

/**
 * @param array<string,mixed> $settings The site's captcha settings.
 */
function renderRegisteredForm(
    Form $form,
    array $attributes,
    array $settings = [],
    ?ProtectedFormRegistry $declared = null,
): string {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->alias(static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES));
    // Mirror WP's attribute encoding so the embedded JSON is realistically escaped.
    Functions\when('esc_attr')->alias(static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES));
    Functions\when('esc_url')->returnArg();
    Functions\when('sanitize_key')->alias(fn (string $key): string => strtolower($key));
    Functions\when('wp_create_nonce')->justReturn('test-nonce');
    // The renderer conditionally enqueues the shared runtime (spec 043); no-op them here.
    Functions\when('wp_enqueue_script')->justReturn(null);
    Functions\when('wp_enqueue_style')->justReturn(null);
    Functions\when('rest_url')->alias(fn (string $path): string => 'https://example.test/wp-json/' . $path);
    Functions\when('wp_json_encode')->alias(static fn ($data): string => (string) json_encode($data));

    $registry = new FormRegistry();
    $registry->register($form);

    $renderer = new FormBlockRenderer(
        $registry,
        new SchemaResolver(new RuleRegistry()),
        new SchemaExporter(),
        new FieldRenderer(),
        challenge: new FormChallengeContextFactory(siteSettings($settings), new RemoteAddress()),
        protectedForms: $declared ?? new ProtectedFormRegistry(),
    );

    return $renderer->render($attributes, '', (object) []);
}

it('renders every field with an associated label, required marker, nonce, and honeypot', function () {
    $html = renderContactForm(['formSlug' => 'contact']);

    expect($html)
        ->toContain('<label for="corex-contact-name"')
        ->toContain('id="corex-contact-name"')
        ->toContain('<label for="corex-contact-email"')
        ->toContain('id="corex-contact-email"')
        ->toContain('type="email"')
        ->toContain('<label for="corex-contact-message"')
        ->toContain('id="corex-contact-message"')
        ->toContain('<textarea')
        ->toContain('aria-required="true"')        // required fields marked for AT
        ->toContain('data-corex-nonce="test-nonce"') // nonce carried for the JS X-WP-Nonce header
        ->toContain('name="corex_hp"');             // honeypot present
});

it('embeds the exported schema and accessible error regions for the shared validator', function () {
    $html = renderContactForm(['formSlug' => 'contact']);

    expect($html)
        ->toContain('data-corex-schema=')                 // schema exported to the client
        ->toContain('&quot;name&quot;:&quot;email&quot;') // a known field is in the embedded JSON
        ->toContain('data-corex-field="email"')           // field wrapper hook for JS targeting
        ->toContain('id="corex-contact-email-error"')     // per-field error region
        ->toContain('aria-describedby="corex-contact-email-error"'); // input points at it
});

it('uses no hardcoded colors or sizes in the rendered markup (token-only)', function () {
    $html = renderContactForm(['formSlug' => 'contact']);

    expect($html)
        ->not->toMatch('/#[0-9a-fA-F]{3,6}\b/') // no hex colors
        ->not->toContain('px');                  // no pixel sizes
});

it('renders nothing for an unknown form slug (non-fatal)', function () {
    expect(renderContactForm(['formSlug' => 'does-not-exist']))->toBe('');
});

// Spec 104, US1 (#248): a designed form wanted "Request a call" on its button and could not say so.
it('reads as a form states it should', function () {
    $html = renderRegisteredForm(
        formThatReads('Request a call', 'Thank you. We will call you.', 'Check the number and try again.'),
        ['formSlug' => 'callback'],
    );

    expect($html)
        ->toContain('<button type="submit" class="corex-form__submit">Request a call</button>')
        ->toContain('data-corex-success="Thank you. We will call you."')
        ->toContain('data-corex-error="Check the number and try again."');
});

it('reads as it always has when a form states no wording', function () {
    $html = renderContactForm(['formSlug' => 'contact']);

    expect($html)
        ->toContain('<button type="submit" class="corex-form__submit">Send</button>')
        ->toContain('data-corex-success="Thank you — your message has been sent."')
        ->toContain('data-corex-error="Please review the highlighted fields and try again."');
});

it('prints markup in stated wording as text', function () {
    $html = renderRegisteredForm(
        formThatReads('<b>Go</b>', 'Done "now" <script>', 'No <i>'),
        ['formSlug' => 'callback'],
    );

    expect($html)
        ->toContain('>&lt;b&gt;Go&lt;/b&gt;</button>')
        ->toContain('data-corex-success="Done &quot;now&quot; &lt;script&gt;"')
        ->toContain('data-corex-error="No &lt;i&gt;"');
});

it('keeps the stock label when a form states a blank one, so the button has a name', function () {
    $html = renderRegisteredForm(formThatReads('   '), ['formSlug' => 'callback']);

    expect($html)->toContain('<button type="submit" class="corex-form__submit">Send</button>');
});

// Spec 104, US2 (#264): only a flow carried the token field and declared itself, so a form
// defined in code got neither a token nor the provider's script.

/** A callback form that does, or does not, say it is protected. */
function callbackForm(bool $protected): Form
{
    return new class($protected) extends Form {
        public string $slug = 'callback';

        protected array $fields = ['phone' => ['type' => 'text', 'rules' => ['required']]];

        public function __construct(private bool $protected)
        {
        }

        public function protection(): array
        {
            return $this->protected ? ['captcha' => 'on'] : parent::protection();
        }
    };
}

it('carries a token field and declares itself when it is protected and a provider is configured', function () {
    $declared = new ProtectedFormRegistry();

    $html = renderRegisteredForm(
        callbackForm(protected: true),
        ['formSlug' => 'callback'],
        ['captcha.driver' => 'recaptcha', 'captcha.secret' => 'a-secret'],
        $declared,
    );

    expect($html)
        ->toContain('<input type="hidden" name="captcha_token" value="" class="corex-form__captcha-token" data-corex-captcha-action="corex_form_callback" />')
        ->and($declared->all())->toBe(['callback' => 'corex_form_callback']);
});

it('carries no token field and declares nothing', function (bool $protected, array $settings) {
    $declared = new ProtectedFormRegistry();

    $html = renderRegisteredForm(callbackForm($protected), ['formSlug' => 'callback'], $settings, $declared);

    expect($html)->not->toContain('captcha_token')
        ->and($declared->isEmpty())->toBeTrue();
})->with([
    'when the form did not ask, whatever the site configured' => [false, ['captcha.driver' => 'recaptcha', 'captcha.secret' => 'a-secret']],
    'when the form asked and the site has no provider'        => [true, []],
]);
