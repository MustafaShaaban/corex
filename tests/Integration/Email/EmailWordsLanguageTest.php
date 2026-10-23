<?php

/**
 * Integration test: the words an email is made of are in the site's language, whoever's request
 * makes them (DECISIONS #362).
 *
 * `EmailLanguageTest` holds what CoreX Mail draws. This holds what is written before a mailer
 * has it: a subject, a line of a body, a label handed over as a value, and the defaults Email
 * Studio stores. Each was translated in the language of the request, which in the admin is the
 * signed-in person's own.
 *
 * Every case runs both ways round, as an admin request of its own: an English site whose
 * administrator reads the admin in Arabic, and an Arabic site whose administrator reads it in
 * English. In such a request a string of CoreX's is `[ar] ` and then the string whenever it is
 * translated in Arabic, so a word says which language it was made in.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Tests\Support\AdministratorsRequest;
use Corex\Tests\Support\MailDelivery;

const COREX_MAIL = 'corex-email/corex-email.php';

/**
 * The two requests, and how a string of CoreX's reads in an email of each site.
 *
 * @return array<string,array{string,string,array{locale:string,is_rtl:bool},Closure(string):string}>
 */
function sitesAndTheirAdministrators(): array
{
    return [
        'an English site, an administrator who reads the admin in Arabic' => [
            'en_US',
            'ar',
            ['locale' => 'ar', 'is_rtl' => true],
            static fn (string $words): string => $words,
        ],
        'an Arabic site, an administrator who reads the admin in English' => [
            'ar',
            'en_US',
            ['locale' => 'en_US', 'is_rtl' => false],
            static fn (string $words): string => '[ar] ' . $words,
        ],
    ];
}

beforeEach(function () {
    // Spec 113: these emails are sent the way a production site sends them. MailDelivery stops
    // each one before the transport.
    $this->productionMail = MailDelivery::answerAs(MailDelivery::DELIVER);
});

afterEach(function () {
    MailDelivery::stop($this->productionMail);
});

it('writes the notification of a form defined in code in the language of the site', function (
    string $siteLanguage,
    string $profileLanguage,
    array $theRequest,
    Closure $inTheSitesLanguage,
) {
    $request = AdministratorsRequest::on($siteLanguage, $profileLanguage)->run(<<<'PHP'
        $forms = new \Corex\Forms\FormRegistry();
        $forms->register(new class extends \Corex\Forms\Form {
            public string $slug = 'corex-words-probe';

            protected array $fields = ['name' => ['type' => 'text'], 'cv' => ['type' => 'file']];
        });
        // A file that was sent with the form and a submission that was stored: said, not made.
        add_filter('get_attached_file', static fn (): string => '/held/curriculum-vitae.pdf');
        $sent = new \Corex\Forms\Submission\FormSubmittedEvent('corex-words-probe', ['name' => 'Salma', 'cv' => 4821]);
        $sent->storedAs(4822);

        (new \Corex\Forms\Listeners\SendEmailListener(
            $container->make(\Corex\Forms\Submission\NotificationDispatcher::class),
            $container->make(\Corex\Support\Config\ConfigInterface::class),
            new \Corex\Forms\Submission\UploadedFileNames($forms),
            $container->make(\Corex\Mail\SubmissionAddress::class),
        ))($sent);

        // And a submission with nothing in it, through the listener as the site makes it.
        ($container->make(\Corex\Forms\Listeners\SendEmailListener::class))(
            new \Corex\Forms\Submission\FormSubmittedEvent('corex-words-probe', []),
        );

        return [];
        PHP);

    [$withAFile, $withNothing] = $request['mail'];

    expect($request['request'])->toBe(['is_admin' => true] + $theRequest)
        ->and($request['mail'])->toHaveCount(2)
        ->and($withAFile['subject'])->toBe($inTheSitesLanguage('New "corex-words-probe" form submission'))
        ->and($withAFile['message'])
            ->toContain('curriculum-vitae.pdf')
            ->toContain($inTheSitesLanguage('The file is opened from the submission, after signing in to the site.'))
            ->toContain('>' . $inTheSitesLanguage('Open the submission') . '</a>')
        ->and($withNothing['subject'])->toBe($inTheSitesLanguage('New "corex-words-probe" form submission'))
        ->and($withNothing['message'])->toContain($inTheSitesLanguage('The submission contained no fields.'))
        // Those five strings are all of CoreX's there is in the two emails: on the Arabic site
        // there is no sixth, and on the English one not one of them is in Arabic.
        ->and(substr_count(implode('', [...array_values($withAFile), ...array_values($withNothing)]), '[ar] '))
            ->toBe($siteLanguage === 'ar' ? 5 : 0)
        ->and($request['afterwards'])->toBe($theRequest);
})->with(sitesAndTheirAdministrators());

it('writes the notification of a flow in the language of the site where CoreX Mail is switched off', function (
    string $siteLanguage,
    string $profileLanguage,
    array $theRequest,
    Closure $inTheSitesLanguage,
) {
    $request = AdministratorsRequest::on($siteLanguage, $profileLanguage)->without(COREX_MAIL)->run(<<<'PHP'
        $now = new \DateTimeImmutable('now');
        $flow = new \Corex\Forms\Flow\Flow(
            id: 1,
            uuid: '3f0c1a52-6b1e-4d2a-9c57-0e6a5f1b7d10',
            slug: 'corex-words-flow',
            name: 'Words',
            description: '',
            state: \Corex\Forms\Flow\Flow::STATE_PUBLISHED,
            ownerId: 1,
            placementType: \Corex\Forms\Flow\Flow::PLACEMENT_NONE,
            placementId: null,
            currentDraftVersion: 1,
            publishedVersion: 1,
            testMode: false,
            createdBy: 1,
            updatedBy: 1,
            createdAt: $now,
            updatedAt: $now,
        );
        $version = new \Corex\Forms\Flow\FlowVersion(1, 1, 1, new \Corex\Forms\Flow\FlowConfiguration(
            schema: [],
            validation: [],
            routing: [],
            emailRoutes: [['enabled' => true, 'event' => 'submitted', 'recipient' => 'team@example.com']],
            success: [],
            placementSnapshot: [],
        ), 1, $now);

        // A flow's notification, written in an admin request. Not a marked test: a test sends no
        // email at all (spec 113). Not stored: there is no submission.
        $container->make(\Corex\Forms\Submission\Stages\EmailStage::class)->execute(
            new \Corex\Forms\Submission\SubmissionPipelineContext($flow, $version, ['name' => 'Salma'], false),
        );

        return ['corex_mail' => $container->has(\Corex\Mail\Mailer::class)];
        PHP);

    expect($request['request'])->toBe(['is_admin' => true] + $theRequest)
        ->and($request['drawn'])->toBe(['corex_mail' => false])
        ->and($request['mail'])->toHaveCount(1)
        ->and($request['mail'][0]['subject'])->toBe($inTheSitesLanguage('New "corex-words-flow" form submission'))
        ->and($request['afterwards'])->toBe($theRequest);
})->with(sitesAndTheirAdministrators());

/**
 * Send a message from the Guides screen's form, as `admin-post.php` does, and report what the
 * person was answered.
 */
const A_SUPPORT_MESSAGE_IS_POSTED = <<<'PHP'
    // Never the address the add-on ships with, even to a mailer that sends nothing.
    add_filter('pre_option_corex_guides_support_email', static fn () => 'support@example.com');
    add_filter('pre_option_corex_guides_support_enabled', static fn () => '1');
    // The words that stand for who wrote are only reached by somebody with no display name.
    wp_get_current_user()->display_name = '';

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        \Corex\Guides\Support\SupportRequestController::NONCE => wp_create_nonce(
            \Corex\Guides\Support\SupportRequestController::ACTION,
        ),
        'corex_guides_category' => 'suggestion',
        'corex_guides_message' => 'Where is the export?',
        'corex_guides_email' => '',
    ];

    $container->make(\Corex\Guides\Support\SupportRequestController::class)->decide();

    // What the form keeps of a message that was sent: its answer, read here, and the minute in
    // which the same person may not send another.
    $answer = $container->make(\Corex\Guides\Support\SupportFlash::class)->take(get_current_user_id());
    delete_transient('corex_guides_support_sent_' . get_current_user_id());

    return ['corex_mail' => $container->has(\Corex\Mail\Mailer::class), 'answer' => $answer];
    PHP;

it('writes a Guides support message in the language of the site, in the design of CoreX Mail', function (
    string $siteLanguage,
    string $profileLanguage,
    array $theRequest,
    Closure $inTheSitesLanguage,
) {
    $request = AdministratorsRequest::on($siteLanguage, $profileLanguage)->run(A_SUPPORT_MESSAGE_IS_POSTED);
    $siteName = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);

    expect($request['request'])->toBe(['is_admin' => true] + $theRequest)
        ->and($request['drawn'])->toBe(['corex_mail' => true, 'answer' => ['outcome' => 'sent', 'message' => '']])
        ->and($request['mail'])->toHaveCount(1)
        // The subject is the template's, and what the message is about is a value handed to it.
        ->and($request['mail'][0]['subject'])->toBe(sprintf(
            $inTheSitesLanguage('[%1$s] Guides support: %2$s'),
            $siteName,
            $inTheSitesLanguage('A suggestion'),
        ))
        ->and($request['mail'][0]['message'])
            ->toContain('>' . $inTheSitesLanguage('A suggestion') . '</td>')
            ->toContain('>' . $inTheSitesLanguage('a signed-in user') . '</td>')
            ->toContain('>' . $inTheSitesLanguage('no address given') . '</td>')
        ->and($request['afterwards'])->toBe($theRequest);
})->with(sitesAndTheirAdministrators());

it('writes a Guides support message in the language of the site where CoreX Mail is switched off', function (
    string $siteLanguage,
    string $profileLanguage,
    array $theRequest,
    Closure $inTheSitesLanguage,
) {
    $request = AdministratorsRequest::on($siteLanguage, $profileLanguage)
        ->without(COREX_MAIL)
        ->run(A_SUPPORT_MESSAGE_IS_POSTED);
    $siteName = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);

    expect($request['request'])->toBe(['is_admin' => true] + $theRequest)
        ->and($request['drawn'])->toBe(['corex_mail' => false, 'answer' => ['outcome' => 'sent', 'message' => '']])
        ->and($request['mail'])->toHaveCount(1)
        ->and($request['mail'][0]['subject'])->toBe(sprintf(
            $inTheSitesLanguage('[%1$s] Guides support: %2$s'),
            $siteName,
            $inTheSitesLanguage('A suggestion'),
        ))
        ->and(explode("\n", $request['mail'][0]['message']))->toBe([
            $inTheSitesLanguage('A CoreX Guides reader sent this from the admin.'),
            '',
            sprintf($inTheSitesLanguage('About: %s'), $inTheSitesLanguage('A suggestion')),
            sprintf($inTheSitesLanguage('From: %s'), $inTheSitesLanguage('a signed-in user')),
            sprintf($inTheSitesLanguage('Reply to: %s'), $inTheSitesLanguage('no address given')),
            sprintf($inTheSitesLanguage('Site: %s'), home_url()),
            '',
            $inTheSitesLanguage('Message:'),
            'Where is the export?',
        ])
        ->and($request['afterwards'])->toBe($theRequest);
})->with(sitesAndTheirAdministrators());

it('stores the defaults of Email Studio in the language of the site', function (
    string $siteLanguage,
    string $profileLanguage,
    array $theRequest,
    Closure $inTheSitesLanguage,
) {
    // A site on which CoreX Mail has only just been switched on: nothing of Email Studio's is
    // stored yet, and this request is the first to reach `init`, where the defaults are stored.
    // The site's own store is neither read nor written, and neither is the mark that says its
    // defaults are in.
    $request = AdministratorsRequest::on($siteLanguage, $profileLanguage)
        ->before(<<<'PHP'
            $hook('init', static function (): void {
                $container = \Corex\Boot::app()->container();
                $container->instance(
                    \Corex\Email\Studio\EmailStudioStore::class,
                    new \Corex\Tests\Support\InMemoryEmailStudioStore(),
                );
                $container->singleton(\Corex\Email\Studio\EmailLayoutRepository::class);
                $container->singleton(\Corex\Email\Studio\EmailPartialRepository::class);
            }, 0, 9);
            $hook('pre_option_corex_email_studio_defaults_version', static fn () => '');
            $hook('pre_update_option_corex_email_studio_defaults_version', static fn ($new, $kept) => $kept, 2);
            PHP)
        ->run(<<<'PHP'
            $partials = [];
            foreach ($container->make(\Corex\Email\Studio\EmailPartialRepository::class)->all() as $partial) {
                $partials[$partial->slug] = [$partial->name, $partial->htmlBody, $partial->plainText];
            }
            $layouts = [];
            foreach ($container->make(\Corex\Email\Studio\EmailLayoutRepository::class)->all() as $layout) {
                $layouts[$layout->slug] = $layout->name;
            }
            ksort($partials);
            ksort($layouts);

            return [
                'store' => get_class($container->make(\Corex\Email\Studio\EmailStudioStore::class)),
                'partials' => $partials,
                'layouts' => $layouts,
            ];
            PHP);

    expect($request['request'])->toBe(['is_admin' => true] + $theRequest)
        ->and($request['drawn']['store'])->toBe(\Corex\Tests\Support\InMemoryEmailStudioStore::class)
        ->and($request['drawn']['partials'])->toBe([
            'footer' => [$inTheSitesLanguage('Footer'), '<p>{{ site.name }}</p>', '{{ site.name }}'],
            'header' => [$inTheSitesLanguage('Header'), '<strong>{{ site.name }}</strong>', '{{ site.name }}'],
            'preferences' => [
                $inTheSitesLanguage('Preferences'),
                '<p><a href="{{ links.preferences }}">' . $inTheSitesLanguage('Preferences') . '</a></p>',
                $inTheSitesLanguage('Preferences: {{ links.preferences }}'),
            ],
            'privacy' => [
                $inTheSitesLanguage('Privacy'),
                '<p><a href="{{ links.privacy }}">' . $inTheSitesLanguage('Privacy') . '</a></p>',
                $inTheSitesLanguage('Privacy: {{ links.privacy }}'),
            ],
            'unsubscribe' => [
                $inTheSitesLanguage('Unsubscribe'),
                '<p><a href="{{ links.unsubscribe }}">' . $inTheSitesLanguage('Unsubscribe') . '</a></p>',
                $inTheSitesLanguage('Unsubscribe: {{ links.unsubscribe }}'),
            ],
        ])
        ->and($request['drawn']['layouts'])->toBe([
            'minimal' => $inTheSitesLanguage('Minimal'),
            'newsletter' => $inTheSitesLanguage('Newsletter'),
            'transactional' => $inTheSitesLanguage('Transactional'),
        ])
        ->and($request['afterwards'])->toBe($theRequest);
})->with(sitesAndTheirAdministrators());

it('deletes what a request of this file stored', function () {
    $request = AdministratorsRequest::on('en_US', 'ar')->run(<<<'PHP'
        return ['id' => wp_insert_post([
            'post_type' => 'corex_email_log',
            'post_status' => 'private',
            'post_title' => 'Kept by nothing',
        ])];
        PHP);

    expect($request['drawn']['id'])->toBeGreaterThan(0)
        ->and(get_post($request['drawn']['id']))->toBeNull();
});
