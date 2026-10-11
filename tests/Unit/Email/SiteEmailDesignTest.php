<?php

/**
 * A site's own email design, and what happens when it fails (spec 106, slice 4: FR-014, FR-016
 * to FR-018).
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Corex\Email\Design\EmailBrand;
use Corex\Email\Design\EmailContent;
use Corex\Email\Design\EmailDesign;
use Corex\Email\Design\EmailDesignFailedEvent;
use Corex\Email\Design\SiteEmailDesign;
use Corex\Email\Design\StandardEmailDesign;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Support\BootLogger;

beforeEach(function () {
    Functions\when('__')->returnArg();
    $this->failures = [];
    $listeners = new ListenerProvider();
    $listeners->listen(EmailDesignFailedEvent::class, function (object $event): void {
        $this->failures[] = $event;
    });
    $this->design = new SiteEmailDesign(new StandardEmailDesign(), new EventDispatcher($listeners, new BootLogger(false)));
    $this->content = new EmailContent('Re: your message', '<p>Thanks, Sam.</p><ul><li>One &amp; two</li></ul>', 'sam@example.com');
    $this->brand = EmailBrand::standard('Acme', 'https://acme.test/');
});

/** A site's design, as a site's plugin would write one. */
function siteDesign(Closure $render): EmailDesign
{
    return new class($render) implements EmailDesign {
        public function __construct(private readonly Closure $render)
        {
        }

        public function render(EmailContent $content, EmailBrand $brand): string
        {
            return ($this->render)($content, $brand);
        }
    };
}

it('draws the standard design where a site gives none', function () {
    $html = $this->design->render($this->content, $this->brand);

    expect($html)->toBe((new StandardEmailDesign())->render($this->content, $this->brand))
        ->and($this->failures)->toBe([]);
});

it('draws the design a site gives, handing it the message, the subject, the brand and who is answered', function () {
    $handed = [];
    Filters\expectApplied('corex_email_design')->once()->andReturn(siteDesign(
        function (EmailContent $content, EmailBrand $brand) use (&$handed): string {
            $handed = [$content->subject, $content->recipient, $brand->name, $brand->direction, $brand->logo];

            return '<html><body><h1>' . $brand->name . '</h1><div class="own">' . $content->bodyHtml . '</div></body></html>';
        },
    ));

    $html = $this->design->render($this->content, $this->brand);

    expect($html)->toBe('<html><body><h1>Acme</h1><div class="own"><p>Thanks, Sam.</p><ul><li>One &amp; two</li></ul></div></body></html>')
        ->and($handed)->toBe(['Re: your message', 'sam@example.com', 'Acme', 'ltr', ''])
        ->and($this->failures)->toBe([]);
});

it('lets a site restyle the message, as long as its words are there in order', function () {
    Filters\expectApplied('corex_email_design')->andReturn(siteDesign(
        fn (EmailContent $content): string => '<html><body>'
            . str_replace(['<p>', '<li>'], ['<p style="color:#123456">', "<li class=\"own\">\n  "], $content->bodyHtml)
            . '</body></html>',
    ));

    $html = $this->design->render($this->content, $this->brand);

    expect($html)->toContain('<p style="color:#123456">Thanks, Sam.</p>')
        ->and($this->failures)->toBe([]);
});

it('falls back to the standard design and says why, when a site design fails', function (Closure $render, string $why) {
    Filters\expectApplied('corex_email_design')->andReturn(siteDesign($render));

    $html = $this->design->render($this->content, $this->brand);

    expect($html)->toBe((new StandardEmailDesign())->render($this->content, $this->brand))
        ->and($this->failures)->toHaveCount(1)
        ->and($this->failures[0]->reason)->toBe($why)
        ->and($this->failures[0]->design)->toContain('EmailDesign@anonymous');
})->with([
    'it throws' => [fn () => throw new RuntimeException('Undefined variable $logo'), EmailDesignFailedEvent::THREW],
    'it answers nothing' => [fn () => " \n", EmailDesignFailedEvent::EMPTY],
    'it leaves the message out' => [fn () => '<html><body><h1>Acme</h1></body></html>', EmailDesignFailedEvent::WITHOUT_MESSAGE],
    'it keeps half the message' => [fn () => '<html><body><p>Thanks, Sam.</p></body></html>', EmailDesignFailedEvent::WITHOUT_MESSAGE],
]);

it('carries what a thrown error said, for whoever has to mend the design', function () {
    Filters\expectApplied('corex_email_design')->andReturn(siteDesign(fn () => throw new RuntimeException('Undefined variable $logo')));

    $this->design->render($this->content, $this->brand);

    expect($this->failures[0]->detail)->toBe('Undefined variable $logo');
});

it('passes over something that is not a design', function () {
    Filters\expectApplied('corex_email_design')->andReturn('<html>not a design</html>');

    $html = $this->design->render($this->content, $this->brand);

    expect($html)->toBe((new StandardEmailDesign())->render($this->content, $this->brand))
        ->and($this->failures)->toHaveCount(1)
        ->and($this->failures[0]->reason)->toBe(EmailDesignFailedEvent::NOT_A_DESIGN);
});
