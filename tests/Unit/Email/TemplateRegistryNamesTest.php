<?php

/**
 * Unit test for TemplateRegistry::names() (spec 063, Phase 2) — the truthful template inventory the
 * Email Studio admin screen reads. No WordPress.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Corex\Email\Template\EmailTemplate;
use Corex\Email\Template\MailContext;
use Corex\Email\Template\TemplateRegistry;

/** A minimal real template for the test — not a mock (test-guard rule 8). */
function namedTemplate(string $name): EmailTemplate
{
    return new class ($name) extends EmailTemplate {
        public function __construct(private readonly string $templateName)
        {
        }

        public function name(): string
        {
            return $this->templateName;
        }

        public function subject(MailContext $context): string
        {
            return '';
        }

        public function body(MailContext $context): string
        {
            return '';
        }
    };
}

it('lists the names of the registered templates in registration order', function () {
    $registry = new TemplateRegistry();
    $registry->register(namedTemplate('contact-notification'));
    $registry->register(namedTemplate('newsletter-confirmation'));

    expect($registry->names())->toBe(['contact-notification', 'newsletter-confirmation']);
});

it('returns an empty list when nothing is registered — never a fabricated template', function () {
    expect((new TemplateRegistry())->names())->toBe([]);
});
