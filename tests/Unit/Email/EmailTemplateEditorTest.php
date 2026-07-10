<?php

/**
 * Unit tests for safe editable Email Studio content and variable schemas (spec 068: FR-116–FR-119).
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Studio\EmailTemplateService;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Email\Studio\EmailPartialRepository;
use Corex\Email\Studio\EmailPartial;
use Corex\Email\Studio\EmailLayout;
use Corex\Email\Studio\EmailStudioStore;
use Corex\Email\Template\Layout;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function emailStudioMemoryStore(): EmailStudioStore
{
    return new class implements EmailStudioStore {
        private array $records = [];
        public function create(string $type, string $slug, string $name, int $parentId, array $payload): int
        {
            $id = count($this->records) + 1;
            $this->records[$id] = compact('id', 'type', 'slug', 'name', 'parentId', 'payload');
            return $id;
        }
        public function update(int $id, string $name, array $payload): bool { return false; }
        public function find(int $id): ?array { return $this->records[$id] ?? null; }
        public function findBySlug(string $type, string $slug): ?array { return null; }
        public function all(string $type, ?int $parentId = null): array
        {
            return array_values(array_filter($this->records, static fn (array $record): bool => $record['type'] === $type));
        }
    };
}

function emailTemplateService(): EmailTemplateService
{
    return new EmailTemplateService([
        'user.name' => ['type' => 'text', 'label' => 'User name'],
        'site.name' => ['type' => 'text', 'label' => 'Site name'],
        'action.url' => ['type' => 'url', 'label' => 'Action URL'],
    ]);
}

function editableVersion(
    string $subject = 'Welcome {{ user.name }}',
    string $html = '<p>Hello {{ user.name }} at {{ site.name }}</p>',
    string $plain = 'Hello {{ user.name }} at {{ site.name }}',
    string $plainMode = 'manual',
): EmailTemplateVersion {
    return new EmailTemplateVersion(
        id: 2,
        templateId: 1,
        versionNumber: 1,
        subject: $subject,
        fromName: 'CoreX',
        fromAddress: 'hello@example.com',
        htmlBody: $html,
        plainText: $plain,
        plainTextMode: $plainMode,
        layoutId: 3,
        layoutVersion: 1,
        variableKeys: ['user.name', 'site.name'],
        createdBy: 7,
        createdAt: new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
        checksum: hash('sha256', $subject . $html . $plain),
    );
}

it('accepts a safe draft whose placeholders match the declared variable schema', function () {
    $errors = emailTemplateService()->validateDraft(editableVersion());

    expect($errors)->toBe([]);
});

it('rejects drafts without a subject or HTML body at the server boundary', function () {
    $errors = emailTemplateService()->validateDraft(editableVersion(subject: ' ', html: ''));

    expect($errors)->toMatchArray([
        'subject' => 'An email subject is required.',
        'html'    => 'Email template HTML is required.',
    ]);
});

it('rejects unknown undeclared and missing variables', function () {
    $version = new EmailTemplateVersion(
        id: 2,
        templateId: 1,
        versionNumber: 1,
        subject: 'Welcome {{ user.password }}',
        fromName: 'CoreX',
        fromAddress: 'hello@example.com',
        htmlBody: '<p>Hello {{ user.name }}</p>',
        plainText: 'Hello {{ user.name }}',
        plainTextMode: 'manual',
        layoutId: 3,
        layoutVersion: 1,
        variableKeys: ['user.name', 'site.name'],
        createdBy: 7,
        createdAt: new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
        checksum: hash('sha256', 'unknown'),
    );

    expect(emailTemplateService()->validateDraft($version))->toMatchArray([
        'variables' => 'Template variables do not match the declared schema.',
    ]);
});

it('rejects scripts event handlers javascript urls PHP and header injection', function (string $subject, string $html) {
    $errors = emailTemplateService()->validateDraft(editableVersion(subject: $subject, html: $html));

    expect($errors)->not->toBe([]);
})->with([
    'script'     => ['Hello', '<script>alert(1)</script>'],
    'handler'    => ['Hello', '<img src="x" onerror="alert(1)">'],
    'javascript' => ['Hello', '<a href="javascript:alert(1)">Go</a>'],
    'php'        => ['Hello', '<?php echo "x"; ?>'],
    'header'     => ["Hello\r\nBcc: victim@example.com", '<p>Hello</p>'],
]);

it('renders context with HTML escaping and keeps subject and plain text free of markup', function () {
    $rendered = emailTemplateService()->render(editableVersion(), [
        'user' => ['name' => '<b>Sam</b>'],
        'site' => ['name' => 'CoreX'],
    ]);

    expect($rendered['html'])->toContain('&lt;b&gt;Sam&lt;/b&gt;')
        ->and($rendered['html'])->not->toContain('<b>Sam</b>')
        ->and($rendered['subject'])->toBe('Welcome Sam')
        ->and($rendered['plain'])->toBe('Hello Sam at CoreX');
});

it('generates plain text from safe HTML when auto mode is selected', function () {
    $version  = editableVersion(html: '<p>Hello <strong>{{ user.name }}</strong></p>', plain: '', plainMode: 'auto');
    $rendered = emailTemplateService()->render($version, [
        'user' => ['name' => 'Sam'],
        'site' => ['name' => 'CoreX'],
    ]);

    expect($rendered['plain'])->toBe('Hello Sam');
});

it('expands active reusable partials before escaping merge values', function () {
    $partials = new EmailPartialRepository(emailStudioMemoryStore());
    $partials->save(new EmailPartial(
        id: 0,
        slug: 'footer',
        name: 'Footer',
        kind: 'footer',
        htmlBody: '<p>{{ site.name }}</p>',
        plainText: '{{ site.name }}',
        status: 'active',
        version: 1,
        createdBy: 7,
        createdAt: new DateTimeImmutable(),
    ));
    $service = new EmailTemplateService([
        'user.name' => ['type' => 'text', 'label' => 'User name'],
        'site.name' => ['type' => 'text', 'label' => 'Site name'],
    ], $partials);
    $version = editableVersion(
        subject: 'Footer',
        html: '<div>Body</div>{{> footer }}',
        plain: 'Body {{> footer }}',
    );

    $rendered = $service->render($version, ['site' => ['name' => '<b>CoreX</b>']]);

    expect($rendered['html'])->toContain('<p>&lt;b&gt;CoreX&lt;/b&gt;</p>')
        ->and($rendered['plain'])->toBe('Body CoreX');
});

it('renders a selected layout with all five regions reusable partials and brand direction', function () {
    $partials = new EmailPartialRepository(emailStudioMemoryStore());
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $partials->save(new EmailPartial(0, 'header', 'Header', 'header', '<header>{{ site.name }}</header>', '{{ site.name }}', 'active', 1, 7, $now));
    $partials->save(new EmailPartial(0, 'footer', 'Footer', 'footer', '<footer>{{ site.name }}</footer>', '{{ site.name }}', 'active', 1, 7, $now));

    $service = new EmailTemplateService([
        'user.name'  => ['type' => 'text', 'label' => 'User name'],
        'site.name'  => ['type' => 'text', 'label' => 'Site name'],
        'action.url' => ['type' => 'url', 'label' => 'Action URL'],
    ], $partials, new Layout(['name' => 'CoreX', 'color' => '#112233', 'dir' => 'rtl']));
    $layout = new EmailLayout(
        id: 4,
        slug: 'newsletter',
        name: 'Newsletter',
        version: 1,
        regions: [
            'header' => '{{> header }}',
            'accent' => '#abcdef',
            'body'   => '<main>{{ content }}</main>',
            'button' => '<a href="{{ action.url }}">Continue</a>',
            'footer' => '{{> footer }}',
        ],
        dependency: null,
        createdBy: 7,
        createdAt: $now,
    );

    $rendered = $service->render(
        editableVersion(subject: 'Welcome', html: '<p>Hello {{ user.name }}</p>', plain: 'Hello {{ user.name }}'),
        [
            'user'   => ['name' => 'Sam'],
            'site'   => ['name' => 'CoreX'],
            'action' => ['url' => 'https://example.com/start?step=1&mode=safe'],
        ],
        $layout,
    );

    expect($rendered['html'])->toContain('<!DOCTYPE html>')
        ->and($rendered['html'])->toContain('<html dir="rtl">')
        ->and($rendered['html'])->toContain('border-block-start:4px solid #abcdef')
        ->and($rendered['html'])->toContain('<header>CoreX</header>')
        ->and($rendered['html'])->toContain('<main><p>Hello Sam</p></main>')
        ->and($rendered['html'])->toContain('href="https://example.com/start?step=1&amp;mode=safe"')
        ->and($rendered['html'])->toContain('<footer>CoreX</footer>');
});
