<?php

/**
 * Unit tests for environment-safe delivery, local capture, and typed attempts (spec 068: FR-112–FR-120).
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Capture\CaptureMailDriver;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\DeliveryDecision;
use Corex\Email\Delivery\DeliveryPolicy;
use Corex\Email\Delivery\EmailAttempt;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Studio\EmailStudioStore;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function deliveryPolicyStore(): EmailStudioStore
{
    return new class implements EmailStudioStore {
        /** @var array<int,array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>}> */
        private array $records = [];

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

it('always captures Development mail without consulting a configured provider', function () {
    $decision = (new DeliveryPolicy())->evaluate('development', providerConfigured: true, liveDeliveryEnabled: true);

    expect($decision->action)->toBe(DeliveryDecision::ACTION_CAPTURE)
        ->and($decision->providerRequired)->toBeFalse()
        ->and($decision->setupPath)->toBeNull();
});

it('blocks Production until both a provider and deliberate live-delivery activation exist', function () {
    $policy = new DeliveryPolicy();

    $missingProvider = $policy->evaluate('production', providerConfigured: false, liveDeliveryEnabled: true);
    $notActivated    = $policy->evaluate('production', providerConfigured: true, liveDeliveryEnabled: false);
    $ready           = $policy->evaluate('production', providerConfigured: true, liveDeliveryEnabled: true);

    expect($missingProvider->action)->toBe(DeliveryDecision::ACTION_BLOCK)
        ->and($missingProvider->setupPath)->toBe('/wp-admin/admin.php?page=corex-settings-config&corex_tab=mail')
        ->and($notActivated->action)->toBe(DeliveryDecision::ACTION_BLOCK)
        ->and($notActivated->reason)->toContain('deliberately enabled')
        ->and($ready->action)->toBe(DeliveryDecision::ACTION_DELIVER)
        ->and($ready->providerRequired)->toBeTrue();
});

it('fails closed for unknown environments', function () {
    $decision = (new DeliveryPolicy())->evaluate('mystery', providerConfigured: false, liveDeliveryEnabled: false);

    expect($decision->action)->toBe(DeliveryDecision::ACTION_BLOCK)
        ->and($decision->reason)->toContain('Unknown environment');
});

it('captures the full local message without invoking a transport', function () {
    $repository = new CapturedEmailRepository(deliveryPolicyStore());
    $driver     = new CaptureMailDriver($repository);
    $message    = new EmailMessage(
        to: ['sam@example.com'],
        cc: [],
        bcc: [],
        replyTo: 'team@example.com',
        subject: 'Welcome Sam',
        body: '<p>Hello Sam</p>',
        headers: ['Content-Type' => 'text/html; charset=UTF-8'],
    );

    expect($driver->send($message))->toBeTrue();

    $captures = $repository->latest();
    expect($captures)->toHaveCount(1)
        ->and($captures[0]->to)->toBe(['sam@example.com'])
        ->and($captures[0]->subject)->toBe('Welcome Sam')
        ->and($captures[0]->body)->toBe('<p>Hello Sam</p>')
        ->and($captures[0]->capturedAt)->toBeInstanceOf(DateTimeImmutable::class);
});

it('persists immutable typed attempts and retry relationships', function () {
    $repository = new EmailAttemptRepository(deliveryPolicyStore());
    $occurredAt = new DateTimeImmutable('2026-07-03T11:00:00+00:00');
    $attempt    = new EmailAttempt(
        id: 0,
        attemptId: 'f6773ddc-2d63-40cc-b408-35c0a81c084b',
        requestId: '64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c',
        parentAttemptId: '13425f8a-92ed-4b08-b04a-7ba6254cefc0',
        recipient: 'sam@example.com',
        subject: 'Welcome Sam',
        templateSlug: 'welcome',
        state: EmailAttempt::STATE_FAILED,
        provider: 'postmark',
        providerEvent: 'timeout',
        retryable: true,
        occurredAt: $occurredAt,
    );

    $saved = $repository->record($attempt);

    expect($saved->id)->toBe(1)
        ->and($repository->findByAttemptId($attempt->attemptId)?->state)->toBe(EmailAttempt::STATE_FAILED)
        ->and($repository->latest(10))->toHaveCount(1)
        ->and($saved->parentAttemptId)->toBe('13425f8a-92ed-4b08-b04a-7ba6254cefc0')
        ->and($saved->recipient)->toBe('s***@example.com')
        ->and($saved->recipientHash)->toMatch('/^[0-9a-f]{64}$/');
});
