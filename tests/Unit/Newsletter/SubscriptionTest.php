<?php

/**
 * Unit tests for the newsletter token signer + subscription lifecycle (spec 013 US1+US2:
 * FR-001..FR-004, SC-001/2/3). Headless, with a fake store + fake mailer.
 *
 * @package Corex\Tests\Unit\Newsletter
 */

declare(strict_types=1);

use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Newsletter\Subscriber\SubscriberStore;
use Corex\Newsletter\Subscription\SubscriptionService;
use Corex\Newsletter\TokenSigner;

final class ArraySubscriberStore implements SubscriberStore
{
    /** @var array<int,array{id:int,email:string,status:string,topics:list<string>}> */
    public array $rows = [];
    private int $auto = 0;

    public function findByEmail(string $email): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['email'] === $email) {
                return $row;
            }
        }

        return null;
    }

    public function create(string $email, array $topics): int
    {
        $id = ++$this->auto;
        $this->rows[$id] = ['id' => $id, 'email' => $email, 'status' => 'pending', 'topics' => $topics];

        return $id;
    }

    public function setStatus(int $id, string $status): void
    {
        $this->rows[$id]['status'] = $status;
    }

    public function confirmedForTopics(array $topics): array
    {
        return [];
    }
}

final class FakeNewsletterMailer implements Mailer
{
    /** @var list<MailRequest> */
    public array $sent = [];

    public function send(MailRequest $request): void
    {
        $this->sent[] = $request;
    }
}

function newsletterService(ArraySubscriberStore $store, FakeNewsletterMailer $mailer): SubscriptionService
{
    return new SubscriptionService($store, new TokenSigner('secret-key'), $mailer);
}

it('signs and verifies a payload, rejecting tampering', function () {
    $signer = new TokenSigner('secret-key');
    $token  = $signer->sign('confirm:a@b.com');

    expect($signer->verify($token))->toBe('confirm:a@b.com')
        ->and($signer->verify($token . 'x'))->toBeNull()
        ->and($signer->verify('garbage'))->toBeNull();
});

it('records a pending subscriber and sends a confirmation on subscribe', function () {
    $store  = new ArraySubscriberStore();
    $mailer = new FakeNewsletterMailer();

    $ok = newsletterService($store, $mailer)->subscribe('A@B.com', ['news'], consent: true);

    expect($ok)->toBeTrue()
        ->and($store->findByEmail('a@b.com')['status'])->toBe('pending')   // normalized + pending
        ->and($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]->templateName)->toBe('newsletter-confirm');
});

it('rejects subscribe without consent or with an invalid email', function () {
    $store  = new ArraySubscriberStore();
    $mailer = new FakeNewsletterMailer();

    expect(newsletterService($store, $mailer)->subscribe('a@b.com', [], consent: false))->toBeFalse()
        ->and(newsletterService($store, $mailer)->subscribe('not-an-email', [], consent: true))->toBeFalse()
        ->and($mailer->sent)->toBe([]);
});

it('confirms a pending subscriber with a valid token only (fail-closed)', function () {
    $store   = new ArraySubscriberStore();
    $service = newsletterService($store, new FakeNewsletterMailer());
    $service->subscribe('a@b.com', ['news'], consent: true);

    expect($service->confirm((new TokenSigner('secret-key'))->sign('confirm:a@b.com')))->toBeTrue()
        ->and($store->findByEmail('a@b.com')['status'])->toBe('confirmed')
        ->and($service->confirm('tampered-token'))->toBeFalse();
});

it('unsubscribes with a valid token and suppresses', function () {
    $store   = new ArraySubscriberStore();
    $service = newsletterService($store, new FakeNewsletterMailer());
    $service->subscribe('a@b.com', ['news'], consent: true);

    expect($service->unsubscribe($service->unsubscribeToken('a@b.com')))->toBeTrue()
        ->and($store->findByEmail('a@b.com')['status'])->toBe('unsubscribed');
});

it('does not duplicate or re-email an already-confirmed subscriber', function () {
    $store   = new ArraySubscriberStore();
    $mailer  = new FakeNewsletterMailer();
    $service = newsletterService($store, $mailer);
    $service->subscribe('a@b.com', ['news'], consent: true);
    $service->confirm((new TokenSigner('secret-key'))->sign('confirm:a@b.com'));
    $mailer->sent = [];

    expect($service->subscribe('a@b.com', ['news'], consent: true))->toBeTrue()
        ->and($mailer->sent)->toBe([])      // confirmed → no new confirmation
        ->and($store->rows)->toHaveCount(1); // no duplicate row
});
