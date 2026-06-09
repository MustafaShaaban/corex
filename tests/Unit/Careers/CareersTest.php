<?php

/**
 * Unit tests for careers (spec 014 US1+US2+US3: FR-001..FR-005, SC-001..4).
 *
 * @package Corex\Tests\Unit\Careers
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Careers\Application\ApplicationService;
use Corex\Careers\Application\ApplicationStore;
use Corex\Careers\Application\StatusFlow;
use Corex\Careers\Block\JobProvider;
use Corex\Careers\Block\JobsRenderer;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Security\Upload\UploadValidator;

final class ArrayApplicationStore implements ApplicationStore
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];
    private int $auto = 0;

    public function create(array $data): int
    {
        $id = ++$this->auto;
        $this->rows[$id] = $data;

        return $id;
    }

    public function setStatus(int $id, string $status): void
    {
        $this->rows[$id]['status'] = $status;
    }
}

final class FakeCareersMailer implements Mailer
{
    /** @var list<MailRequest> */
    public array $sent = [];

    public function send(MailRequest $request): void
    {
        $this->sent[] = $request;
    }
}

function applicationService(ArrayApplicationStore $store, FakeCareersMailer $mailer): ApplicationService
{
    return new ApplicationService(
        $store,
        new UploadValidator(['application/pdf' => ['pdf']], 2 * 1024 * 1024),
        $mailer,
        'hr@example.com',
    );
}

/**
 * @return array<string,mixed>
 */
function validCv(): array
{
    return ['name' => 'cv.pdf', 'type' => 'application/pdf', 'size' => 100_000, 'error' => UPLOAD_ERR_OK];
}

it('allows only valid pipeline transitions', function () {
    $flow = new StatusFlow();

    expect($flow->canTransition('new', 'reviewing'))->toBeTrue()
        ->and($flow->canTransition('new', 'rejected'))->toBeTrue()
        ->and($flow->canTransition('new', 'hired'))->toBeFalse()        // not adjacent
        ->and($flow->canTransition('hired', 'reviewing'))->toBeFalse()  // terminal
        ->and($flow->nextStatuses('offer'))->toBe(['hired', 'rejected']);
});

it('stores a valid application and notifies HR + the applicant', function () {
    $store  = new ArrayApplicationStore();
    $mailer = new FakeCareersMailer();

    $result = applicationService($store, $mailer)->apply(7, ['name' => 'Mustafa', 'email' => 'm@example.com', 'cover_letter' => 'Hi'], validCv());

    expect($result->stored)->toBeTrue()
        ->and($store->rows)->toHaveCount(1)
        ->and($store->rows[$result->id]['status'])->toBe('new')
        ->and($mailer->sent)->toHaveCount(2)
        ->and($mailer->sent[0]->to)->toBe(['hr@example.com'])
        ->and($mailer->sent[1]->to)->toBe(['m@example.com']);
});

it('rejects an invalid field or a bad CV with zero side effects', function () {
    $store  = new ArrayApplicationStore();
    $mailer = new FakeCareersMailer();
    $service = applicationService($store, $mailer);

    expect($service->apply(7, ['name' => '', 'email' => 'm@example.com'], validCv())->reason)->toBe('invalid_fields')
        ->and($service->apply(7, ['name' => 'A', 'email' => 'bad'], validCv())->reason)->toBe('invalid_fields')
        ->and($service->apply(7, ['name' => 'A', 'email' => 'a@b.com'], ['name' => 'x.exe', 'type' => 'application/x-msdownload', 'size' => 1000, 'error' => UPLOAD_ERR_OK])->stored)->toBeFalse()
        ->and($store->rows)->toBe([])
        ->and($mailer->sent)->toBe([]);
});

it('renders open jobs as accessible cards, with an empty state', function () {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_html__')->returnArg();

    $provider = new class implements JobProvider {
        public function openJobs(int $count): array
        {
            return [['title' => 'Engineer', 'url' => 'https://x/1', 'department' => 'Eng', 'location' => 'Remote', 'type' => 'Full-time']];
        }
    };

    $html = (new JobsRenderer($provider))->render([], '', (object) []);
    expect($html)->toContain('Engineer')->toContain('<article')->toContain('Eng · Remote · Full-time');

    $empty = new class implements JobProvider {
        public function openJobs(int $count): array
        {
            return [];
        }
    };
    expect((new JobsRenderer($empty))->render([], '', (object) []))->toContain('corex-jobs__empty');
});
