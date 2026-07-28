<?php

/**
 * A form's listeners belong to that form — issue #138, item 1.
 *
 * `FormsServiceProvider::registerListeners()` used to walk every registered form at boot and
 * register each distinct listener id **once, deduplicated across all forms**, onto the shared
 * `FormSubmittedEvent`. The resulting list was global: as soon as any form declared a listener, that
 * listener ran for every submission on the site, and overriding `Form::listeners()` to *remove* one
 * did nothing. A site replacing the built-in notification with its own therefore sent two emails
 * per submission — which is how this was found, on a real build.
 *
 * Integration rather than unit, because the defect lived in the wiring: the provider, the registry,
 * the container and the event dispatcher had to be assembled the way a real request assembles them
 * before it could be seen at all.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Events\EventDispatcher;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Submission\FormSubmittedEvent;

/** Records that it ran, and for which form. */
abstract class RecordingListener
{
    /** @var list<string> */
    public static array $calls = [];

    public function __invoke(FormSubmittedEvent $event): void
    {
        self::$calls[] = static::class . '@' . $event->formSlug;
    }
}

final class LoudListener extends RecordingListener
{
}

final class QuietListener extends RecordingListener
{
}

/** Declares the loud listener. */
final class LoudForm extends Form
{
    public string $slug = 'corex-test-loud';

    public function listeners(): array
    {
        return [LoudListener::class];
    }
}

/** Declares a different one — and must not inherit LoudForm's. */
final class QuietForm extends Form
{
    public string $slug = 'corex-test-quiet';

    public function listeners(): array
    {
        return [QuietListener::class];
    }
}

/** Declares none at all, which the old wiring made impossible to express. */
final class SilentForm extends Form
{
    public string $slug = 'corex-test-silent';

    public function listeners(): array
    {
        return [];
    }
}

beforeEach(function () {
    RecordingListener::$calls = [];

    $this->container = Boot::app()->container();
    $this->registry  = $this->container->make(FormRegistry::class);

    foreach ([LoudForm::class, QuietForm::class, SilentForm::class] as $form) {
        $this->registry->register($this->container->make($form));
    }

    $this->submit = fn (string $slug) => $this->container
        ->make(EventDispatcher::class)
        ->dispatch(new FormSubmittedEvent($slug, ['name' => 'Ada']));
});

it('runs only the submitted form\'s listeners', function () {
    ($this->submit)('corex-test-loud');

    expect(RecordingListener::$calls)->toBe([LoudListener::class . '@corex-test-loud']);
});

it('does not run one form\'s listener for another form\'s submission', function () {
    // The defect in one assertion: before the fix, submitting the quiet form ran the loud
    // listener too, because the registration list was global.
    ($this->submit)('corex-test-quiet');

    expect(RecordingListener::$calls)->toBe([QuietListener::class . '@corex-test-quiet'])
        ->and(RecordingListener::$calls)->not->toContain(LoudListener::class . '@corex-test-quiet');
});

it('lets a form remove every listener by overriding listeners()', function () {
    // `Form::listeners()` documents itself as overridable by concrete forms. Removal was the half
    // that never worked: a dropped listener was still registered by some other form.
    ($this->submit)('corex-test-silent');

    expect(RecordingListener::$calls)->toBe([]);
});

it('ignores a slug no registered form answers to', function () {
    // A database-defined flow rather than a code-defined form. Matching nothing is the correct
    // answer; the alternative — falling back to every listener — is the bug in a different shape.
    ($this->submit)('corex-test-not-a-form');

    expect(RecordingListener::$calls)->toBe([]);
});
