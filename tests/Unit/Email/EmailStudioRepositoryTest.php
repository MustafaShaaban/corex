<?php

/**
 * Unit tests for editable Email Studio assets and immutable template versions (spec 068: FR-113–FR-119).
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Studio\EmailLayoutRepository;
use Corex\Email\Studio\EmailPartialRepository;
use Corex\Email\Studio\EmailPartial;
use Corex\Email\Studio\EmailStudioStore;
use Corex\Email\Studio\EmailTemplate;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateVersion;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
});

function emailStudioStore(): EmailStudioStore
{
    return new class implements EmailStudioStore {
        /** @var array<int,array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>}> */
        public array $records = [];

        public function create(string $type, string $slug, string $name, int $parentId, array $payload): int
        {
            $id = count($this->records) + 1;
            $this->records[$id] = compact('id', 'type', 'slug', 'name', 'parentId', 'payload');

            return $id;
        }

        public function update(int $id, string $name, array $payload): bool
        {
            if (! isset($this->records[$id])) {
                return false;
            }

            $this->records[$id]['name']    = $name;
            $this->records[$id]['payload'] = $payload;

            return true;
        }

        public function find(int $id): ?array
        {
            return $this->records[$id] ?? null;
        }

        public function findBySlug(string $type, string $slug): ?array
        {
            foreach ($this->records as $record) {
                if ($record['type'] === $type && $record['slug'] === $slug) {
                    return $record;
                }
            }

            return null;
        }

        public function all(string $type, ?int $parentId = null): array
        {
            return array_values(array_filter(
                $this->records,
                static fn (array $record): bool => $record['type'] === $type
                    && ($parentId === null || $record['parentId'] === $parentId),
            ));
        }
    };
}

function repositoryDraft(int $templateId, string $subject, DateTimeImmutable $now): EmailTemplateVersion
{
    $variableKeys = str_contains($subject, '{{ user.name }}') ? ['user.name'] : [];

    return new EmailTemplateVersion(
        id: 1,
        templateId: $templateId,
        versionNumber: 1,
        subject: $subject,
        fromName: 'CoreX',
        fromAddress: 'hello@example.com',
        htmlBody: '<p>' . $subject . '</p>',
        plainText: $subject,
        plainTextMode: 'manual',
        layoutId: 1,
        layoutVersion: 1,
        variableKeys: $variableKeys,
        createdBy: 7,
        createdAt: $now,
        checksum: hash('sha256', $subject),
    );
}

function repositoryPartial(string $slug, string $html, string $plain, DateTimeImmutable $now): EmailPartial
{
    return new EmailPartial(
        id: 0,
        slug: $slug,
        name: ucfirst($slug),
        kind: 'custom',
        htmlBody: $html,
        plainText: $plain,
        status: 'active',
        version: 1,
        createdBy: 7,
        createdAt: $now,
    );
}

it('creates a template, appends immutable draft versions, and activates one version', function () {
    $store      = emailStudioStore();
    $repository = new EmailTemplateRepository($store);
    $now        = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $template   = $repository->create('welcome', 'Welcome', 7, $now);

    $first = $repository->saveDraft(repositoryDraft($template->id, 'Welcome {{ user.name }}', $now));
    $second = $repository->saveDraft(repositoryDraft(
        $template->id,
        'Welcome back {{ user.name }}',
        $now->modify('+1 minute'),
    ));
    $active = $repository->activate($template->id, $first->versionNumber, 7, $now->modify('+2 minutes'));

    expect($template->status)->toBe(EmailTemplate::STATUS_DRAFT)
        ->and($first->versionNumber)->toBe(1)
        ->and($second->versionNumber)->toBe(2)
        ->and($first->checksum)->not->toBe($second->checksum)
        ->and($repository->versions($template->id))->toHaveCount(2)
        ->and($active->status)->toBe(EmailTemplate::STATUS_ACTIVE)
        ->and($active->activeVersion)->toBe(1)
        ->and($active->draftVersion)->toBe(2);
});

it('rejects duplicate template slugs and activation of a foreign version', function () {
    $store      = emailStudioStore();
    $repository = new EmailTemplateRepository($store);
    $now        = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $first      = $repository->create('welcome', 'Welcome', 7, $now);
    $second     = $repository->create('receipt', 'Receipt', 7, $now);
    $version    = $repository->saveDraft(repositoryDraft($second->id, 'Receipt', $now));

    expect(fn () => $repository->create('welcome', 'Duplicate', 7, $now))
        ->toThrow(DomainException::class)
        ->and(fn () => $repository->activate($first->id, $version->versionNumber, 7, $now))
        ->toThrow(DomainException::class);
});

it('installs three native layouts and gates the Woo layout on dependency availability', function () {
    $withoutWoo = new EmailLayoutRepository(emailStudioStore());
    $withWoo    = new EmailLayoutRepository(emailStudioStore());
    $now        = new DateTimeImmutable('2026-07-03T10:00:00+00:00');

    $withoutWoo->installDefaults(false, 7, $now);
    $withWoo->installDefaults(true, 7, $now);

    expect(array_map(static fn ($layout): string => $layout->slug, $withoutWoo->all()))->toBe([
        'transactional',
        'minimal',
        'newsletter',
    ])->and(array_map(static fn ($layout): string => $layout->slug, $withWoo->all()))
        ->toContain('woocommerce')
        ->and(array_keys($withoutWoo->find('transactional')?->regions ?? []))->toBe([
            'header',
            'accent',
            'body',
            'button',
            'footer',
        ]);
});

it('upgrades a legacy default layout into the structured region schema once', function () {
    $store = emailStudioStore();
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $store->create('email_layout', 'transactional', 'Transactional', 0, [
        'asset_slug' => 'transactional',
        'version' => 1,
        'regions' => [
            'header' => '{{> header }}',
            'accent' => '',
            'body' => '{{ content }}',
            'button' => '',
            'footer' => '{{> footer }}',
        ],
        'dependency' => null,
        'created_by' => 7,
        'created_at' => $now->format(DATE_ATOM),
    ]);
    $repository = new EmailLayoutRepository($store);

    $repository->installDefaults(false, 7, $now->modify('+1 minute'));
    $repository->installDefaults(false, 7, $now->modify('+2 minutes'));

    expect($repository->versions('transactional'))->toHaveCount(2)
        ->and($repository->find('transactional')?->regions['body'])->toBe('{{ content }}')
        ->and($repository->find('transactional')?->status)->toBe('active');
});

it('persists reusable partial revisions without overwriting history', function () {
    $repository = new EmailPartialRepository(emailStudioStore());
    $now        = new DateTimeImmutable('2026-07-03T10:00:00+00:00');

    $first  = $repository->save(repositoryPartial('signature', '<p>Team</p>', 'Team', $now));
    $second = $repository->save(repositoryPartial('signature', '<p>CoreX Team</p>', 'CoreX Team', $now));

    expect($first->version)->toBe(1)
        ->and($second->version)->toBe(2)
        ->and($repository->versions('signature'))->toHaveCount(2)
        ->and($repository->find('signature')?->htmlBody)->toBe('<p>CoreX Team</p>');
});

it('installs the five reusable system partials idempotently', function () {
    $repository = new EmailPartialRepository(emailStudioStore());
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');

    $repository->installDefaults(0, $now);
    $repository->installDefaults(0, $now);

    expect(array_map(static fn ($partial): string => $partial->slug, $repository->all()))->toBe([
        'header',
        'footer',
        'unsubscribe',
        'preferences',
        'privacy',
    ]);
});
