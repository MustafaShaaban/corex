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
use Corex\Forms\Block\FormParts;
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

// Spec 104, US3 (SC-007). The stock form is about to be composed from parts a site may also use.
// What a form that supplies no markup of its own prints must not move by a byte: these two files
// were written from the renderer as it stood before that change.

/** One field of every kind the renderer draws, with each presentation knob used somewhere. */
function everyFieldForm(): Form
{
    return new class extends Form {
        public string $slug = 'every-field';

        protected array $fields = [
            'intro'    => ['type' => 'step', 'label' => 'About you', 'help_text' => 'Two minutes.'],
            'name'     => ['type' => 'text', 'label' => 'Name', 'rules' => ['required', 'max_length:120'], 'placeholder' => 'Your name'],
            'email'    => ['type' => 'email', 'label' => 'Email', 'rules' => ['required', 'email'], 'width' => 'half'],
            'phone'    => ['type' => 'phone', 'label' => 'Phone', 'rules' => ['phone:national'], 'width' => 'half', 'help_text' => 'With its area code.'],
            'site'     => ['type' => 'url', 'label' => 'Website', 'label_mode' => 'hidden'],
            'age'      => ['type' => 'number', 'label' => 'Age', 'rules' => ['numeric', 'min:18'], 'class' => 'is-narrow'],
            'secret'   => ['type' => 'password', 'label' => 'Passphrase'],
            'day'      => ['type' => 'date', 'label' => 'Day'],
            'hour'     => ['type' => 'time', 'label' => 'Hour'],
            'cv'       => ['type' => 'file', 'label' => 'CV', 'rules' => ['mime:application/pdf']],
            'source'   => ['type' => 'hidden', 'default_value' => 'landing'],
            'message'  => ['type' => 'textarea', 'label' => 'Message', 'rules' => ['required'], 'attrs' => ['rows' => '6']],
            'topic'    => ['type' => 'select', 'label' => 'Topic', 'options' => ['sales' => 'Sales', 'help' => 'Help'], 'default_value' => 'help'],
            'tags'     => ['type' => 'multi-select', 'label' => 'Tags', 'options' => ['a' => 'A', 'b' => 'B']],
            'contact'  => ['type' => 'radio', 'label' => 'Contact by', 'options' => ['email' => 'Email', 'phone' => 'Phone'], 'rules' => ['required']],
            'days'     => ['type' => 'checkbox-group', 'label' => 'Days', 'options' => ['mon' => 'Monday', 'tue' => 'Tuesday']],
            'agree'    => ['type' => 'checkbox', 'label' => 'I agree', 'rules' => ['required']],
            'news'     => ['type' => 'toggle', 'label' => 'Send me news', 'label_mode' => 'inline'],
            'consent'  => ['type' => 'consent', 'label' => 'I consent'],
            'stars'    => ['type' => 'rating', 'label' => 'Rating'],
        ];
    };
}

it('prints the stock form as it always has', function (Form $form, string $recorded) {
    $html = renderRegisteredForm($form, ['formSlug' => $form->slug]);

    expect($html)->toBe(file_get_contents(dirname(__DIR__, 2) . '/fixtures/Forms/' . $recorded));
})->with([
    'the shipped contact form'       => [fn (): Form => new ContactForm(), 'stock-contact-form.html'],
    'a form with every kind of field' => [fn (): Form => everyFieldForm(), 'stock-every-field-form.html'],
]);

// Spec 104, US3 (#248): a form whose design is not the stock form's draws itself from the parts.

/** A lead form drawn as a card: one control written by hand, one stock field, its own classes. */
function handDrawnForm(): Form
{
    return new class extends Form {
        public string $slug = 'lead';

        protected array $fields = [
            'phone' => ['type' => 'phone', 'label' => 'Phone', 'rules' => ['required']],
            'note'  => ['type' => 'textarea', 'label' => 'Note'],
        ];

        public function submitLabel(): string
        {
            return 'Request a call';
        }

        public function markup(FormParts $parts): ?string
        {
            return '<form ' . $parts->attributes(['class' => 'lead-card']) . '>'
                . '<div class="lead-card__row" ' . $parts->fieldAttributes('phone') . '>'
                . $parts->label('phone')
                . '<input type="tel" class="lead-card__input" ' . $parts->control('phone') . ' />'
                . $parts->error('phone')
                . '</div>'
                . $parts->field('note')
                . $parts->hidden()
                . '<footer class="lead-card__foot">' . $parts->submit(['class' => 'lead-card__go']) . $parts->status() . '</footer>'
                . '</form>';
        }
    };
}

/** A form whose markup forgot the status place and the hidden fields. */
function formWithPartsMissing(): Form
{
    return new class extends Form {
        public string $slug = 'broken';

        protected array $fields = ['phone' => ['type' => 'text']];

        public function label(): string
        {
            return 'Broken form';
        }

        public function markup(FormParts $parts): ?string
        {
            return '<form ' . $parts->attributes() . '>' . $parts->field('phone') . $parts->submit() . '</form>';
        }
    };
}

it('shows a form its own markup in place of the stock form', function () {
    $html = renderRegisteredForm(handDrawnForm(), ['formSlug' => 'lead']);

    expect($html)
        ->toStartWith('<form class="corex-form lead-card" method="post" novalidate')
        ->toContain('<div class="lead-card__row" data-corex-field="phone" data-corex-visibility="visible">')
        ->toContain('<input type="tel" class="lead-card__input" id="corex-lead-phone" name="phone" aria-describedby="corex-lead-phone-error" required aria-required="true" />')
        ->toContain('<button type="submit" class="corex-form__submit lead-card__go">Request a call</button>')
        // The same page the browser tests of the runtime are run against: see handDrawnForm.test.js.
        ->toBe(file_get_contents(dirname(__DIR__, 2) . '/fixtures/Forms/hand-drawn-form.html'));
});

it('shows a visitor nothing when a form\'s markup is missing a part, and says which to the developer', function () {
    Functions\when('current_user_can')->justReturn(false);
    $reported = [];
    Functions\when('_doing_it_wrong')->alias(function (string $where, string $what) use (&$reported): void {
        $reported[] = $what;
    });

    $html = renderRegisteredForm(formWithPartsMissing(), ['formSlug' => 'broken']);

    expect($html)->toBe('')
        ->and($reported)->toBe(['The markup of the form &quot;broken&quot; is missing: hidden(), status().']);
});

it('tells somebody who can edit the page which parts a form\'s markup is missing', function () {
    Functions\when('current_user_can')->alias(static fn (string $capability): bool => $capability === 'edit_posts');
    Functions\when('_doing_it_wrong')->justReturn(null);

    $html = renderRegisteredForm(formWithPartsMissing(), ['formSlug' => 'broken']);

    expect($html)->toBe(
        '<p class="corex-form__notice" role="alert">The form &quot;Broken form&quot; is not shown to visitors: its markup is missing hidden(), status().</p>',
    );
});
