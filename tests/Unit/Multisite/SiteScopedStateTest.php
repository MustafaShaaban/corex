<?php

/**
 * Regression coverage for spec 100 Phase 7 site-scoped state conversions.
 *
 * @package Corex\Tests\Unit\Multisite
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

use Brain\Monkey\Functions;
use Corex\Assets\AssetManager;
use Corex\Assets\AssetsServiceProvider;
use Corex\Careers\Application\ApplicationService;
use Corex\Careers\Application\ApplicationStore;
use Corex\Config\Branding\BrandingService;
use Corex\Config\ConfigServiceProvider;
use Corex\Container\Container;
use Corex\Database\QueryBuilder;
use Corex\Database\QueryExecutor;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Multisite\SiteScope;
use Corex\Repositories\Hydrator;
use Corex\Security\Upload\AttachmentResult;
use Corex\Security\Upload\AttachmentStorage;
use Corex\Security\Upload\UploadValidator;
use Corex\Support\Config\ConfigInterface;
use Corex\Tests\Fixtures\Data\FakeFieldDriver;
use Corex\Tests\Fixtures\Data\Job;
use Corex\Tests\Fixtures\Multisite\SiteScopeEnvironment;

require_once dirname(__DIR__) . '/Data/DataFixtures.php';

final class PhaseSevenConfig implements ConfigInterface
{
    public int $siteId = 1;

    /** @param array<int,array<string,mixed>> $siteValues */
    public function __construct(private readonly array $siteValues)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->siteValues[$this->siteId][$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->siteValues[$this->siteId] ?? []);
    }
}

final class PhaseSevenApplicationStore implements ApplicationStore
{
    public function create(array $data): int
    {
        return 1;
    }

    public function setStatus(int $id, string $status): void
    {
    }
}

final class PhaseSevenMailer implements Mailer
{
    /** @var list<MailRequest> */
    public array $sent = [];

    public function send(MailRequest $request): void
    {
        $this->sent[] = $request;
    }
}

final class PhaseSevenAttachmentStore implements AttachmentStorage
{
    public function store(array $file, string $context = ''): AttachmentResult
    {
        return AttachmentResult::stored(42);
    }

    public function forget(int $attachmentId): bool
    {
        return true;
    }
}

it('reads the query cap where the limit is applied after a site switch', function () {
    $environment = new SiteScopeEnvironment();
    $config = new PhaseSevenConfig([
        1 => ['query.max' => 100],
        2 => ['query.max' => 25],
    ]);
    $executor = new QueryExecutor(new Hydrator(new FakeFieldDriver()), $config);
    $query = new QueryBuilder(Job::class, $executor);

    expect($query->toArgs()['posts_per_page'])->toBe(100);

    $config->siteId = 2;
    $environment->switchTo(2);

    expect($query->toArgs()['posts_per_page'])->toBe(25);
});

it('recomputes the asset base URL through its provider registration after a site switch', function () {
    if (! defined('COREX_CORE_PATH')) {
        define('COREX_CORE_PATH', dirname(__DIR__, 3) . '/plugins/corex-core/');
    }
    if (! defined('COREX_CORE_FILE')) {
        define('COREX_CORE_FILE', COREX_CORE_PATH . 'corex-core.php');
    }
    if (! defined('COREX_CORE_VERSION')) {
        define('COREX_CORE_VERSION', 'test');
    }

    $siteId = 1;
    Functions\when('plugins_url')->alias(
        static function (string $path = '', string $plugin = '') use (&$siteId): string {
            return "https://site-{$siteId}.test/corex-core";
        },
    );

    $environment = new SiteScopeEnvironment();
    $container = new Container();
    $container->instance(ConfigInterface::class, new PhaseSevenConfig([]));
    $container->instance(SiteScope::class, $environment->scope);
    (new AssetsServiceProvider($container))->register();
    $manager = $container->make(AssetManager::class);

    expect($manager->url('build/app.css'))->toBe('https://site-1.test/corex-core/build/app.css');

    $siteId = 2;
    $environment->switchTo(2);

    expect($manager->url('build/app.css'))->toBe('https://site-2.test/corex-core/build/app.css');
});

it('recomputes the bundled branding URL through its provider registration after a site switch', function () {
    $siteId = 1;
    Functions\when('plugins_url')->alias(
        static function (string $path = '', string $plugin = '') use (&$siteId): string {
            return "https://site-{$siteId}.test/{$path}";
        },
    );

    $environment = new SiteScopeEnvironment();
    $container = new Container();
    $container->instance(ConfigInterface::class, new PhaseSevenConfig([]));
    $container->instance(SiteScope::class, $environment->scope);
    (new ConfigServiceProvider($container))->register();
    $branding = $container->make(BrandingService::class);

    expect($branding->logoUrl())->toBe('https://site-1.test/assets/brand/corex-lockup.svg');

    $siteId = 2;
    $environment->switchTo(2);

    expect($branding->logoUrl())->toBe('https://site-2.test/assets/brand/corex-lockup.svg');
});

it('reads the fallback HR email when sending after a site switch', function () {
    $siteId = 1;
    Functions\when('get_option')->alias(
        static function (string $key) use (&$siteId): string {
            return $siteId === 1 ? 'hr@site-one.test' : 'hr@site-two.test';
        },
    );

    $environment = new SiteScopeEnvironment();
    $mailer = new PhaseSevenMailer();
    $service = new ApplicationService(
        new PhaseSevenApplicationStore(),
        new UploadValidator(['application/pdf' => ['pdf']], 1024),
        new PhaseSevenAttachmentStore(),
        $mailer,
        new PhaseSevenConfig([]),
    );
    $cv = ['name' => 'cv.pdf', 'type' => 'application/pdf', 'size' => 100, 'error' => UPLOAD_ERR_OK];

    $service->apply(1, ['name' => 'One', 'email' => 'one@example.test'], $cv);
    expect($mailer->sent[0]->to)->toBe(['hr@site-one.test']);

    $siteId = 2;
    $environment->switchTo(2);
    $service->apply(2, ['name' => 'Two', 'email' => 'two@example.test'], $cv);

    expect($mailer->sent[2]->to)->toBe(['hr@site-two.test']);
});
