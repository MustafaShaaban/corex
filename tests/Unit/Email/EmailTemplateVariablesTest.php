<?php

/**
 * Every variable has a value or is flagged (spec 120, story 5), and a template's sender and plain
 * text are checked and written as they are sent (story 2).
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Studio\EmailTemplateService;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Email\Studio\EmailVariableCatalog;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $one, string $many, int $count): string => $count === 1 ? $one : $many);
});

function variablesVersion(string $html, string $plain = '', string $mode = 'auto', string $from = '', string $fromName = '', string $subject = 'Hello'): EmailTemplateVersion
{
    return new EmailTemplateVersion(
        id: 1,
        templateId: 1,
        versionNumber: 1,
        subject: $subject,
        fromName: $fromName,
        fromAddress: $from,
        htmlBody: $html,
        plainText: $plain,
        plainTextMode: $mode,
        layoutId: 1,
        layoutVersion: 1,
        variableKeys: [],
        createdBy: 1,
        createdAt: new DateTimeImmutable('2026-10-23'),
        checksum: hash('sha256', $html . $plain),
    );
}

function variablesService(): EmailTemplateService
{
    return new EmailTemplateService(new EmailVariableCatalog(['recipient.name' => ['type' => 'text', 'label' => 'Recipient name']]));
}

it('knows a form\'s every field, CoreX\'s own variables and the configured ones, and names an unknown one', function () {
    $catalog = new EmailVariableCatalog(['recipient.name' => ['type' => 'text', 'label' => 'Recipient name']]);

    expect($catalog->isKnown('submission.message'))->toBeTrue()
        ->and($catalog->isKnown('submission.phone_number'))->toBeTrue()
        ->and($catalog->isKnown('form.answers'))->toBeTrue()
        ->and($catalog->isKnown('recipient.name'))->toBeTrue()
        ->and($catalog->isKnown('nonsense.key'))->toBeFalse()
        ->and(variablesService()->validateDraft(variablesVersion('<p>{{ submission.message }}</p>')))->toBe([])
        ->and(variablesService()->validateDraft(variablesVersion('<p>{{ nonsense.key }} {{ other.thing }}</p>')))
        ->toBe(['variables' => 'Unknown variables: nonsense.key, other.thing.']);
});

it('lists the variables by the emails that supply them, and what no CoreX email supplies apart', function () {
    $groups = array_column((new EmailVariableCatalog(['recipient.name' => ['type' => 'text', 'label' => 'x']]))->groups(), 'variables', 'key');

    expect(array_keys($groups))->toBe(['every', 'form', 'newsletter', 'account', 'access', 'other'])
        ->and(array_column($groups['form'], 'key'))->toContain('form.answers', 'links.submission')
        ->and(array_column($groups['other'], 'key'))->toBe(['recipient.name']);
});

it('warns of each variable the emails a template is sent as do not supply', function () {
    $version = variablesVersion('<p>{{ user.name }} {{ submission.phone }} {{ site.name }}</p>');

    expect(variablesService()->unsupplied($version, [EmailVariableCatalog::FORM]))
        ->toBe(['user.name' => 'user.name is not available in a form email.'])
        ->and(variablesService()->unsupplied($version, [EmailVariableCatalog::ACCOUNT]))
        ->toBe(['submission.phone' => 'submission.phone is not available in an account email.'])
        ->and(variablesService()->unsupplied($version, []))->toBe([]);
});

it('draws every answer, labelled, in the form\'s order, a file answer as a link, and a value never as markup', function () {
    $drawn = variablesService()->render(variablesVersion('<p>Answers:</p>{{ form.answers }}'), ['form' => ['answers' => [
        ['label' => 'Name', 'value' => '<b>hi</b>'],
        ['label' => 'CV', 'value' => 'cv.pdf', 'url' => 'https://acme.test/wp-admin/submission/4'],
        ['label' => 'Bad', 'value' => 'x', 'url' => 'javascript:alert(1)'],
    ]]]);

    expect($drawn['html'])->toContain('<th style="padding:6px 16px 6px 0;vertical-align:top;text-align:start;font-weight:600">Name</th>')
        ->and($drawn['html'])->toContain('&lt;b&gt;hi&lt;/b&gt;')
        ->and($drawn['html'])->toContain('<a href="https://acme.test/wp-admin/submission/4">cv.pdf</a>')
        ->and($drawn['html'])->not->toContain('javascript:')
        ->and($drawn['plain'])->toBe("Answers:\n\nName: <b>hi</b>\nCV: cv.pdf (https://acme.test/wp-admin/submission/4)\nBad: x")
        ->and($drawn['missing'])->toBe([]);
});

it('leaves out a label whose value is missing and keeps a sentence, naming what was missing', function () {
    $drawn = variablesService()->render(
        variablesVersion('<p>Phone: {{ submission.phone }}</p><table><tr><th>Company</th><td>{{ submission.company }}</td></tr></table><p>Thanks {{ submission.name }}, we will call.</p>'),
        [],
    );

    expect($drawn['html'])->not->toContain('Phone')
        ->and($drawn['html'])->not->toContain('Company')
        ->and($drawn['html'])->toContain('<p>Thanks , we will call.</p>')
        ->and($drawn['missing'])->toBe(['submission.phone', 'submission.company', 'submission.name']);
});

it('keeps paragraphs, lists and link addresses in the plain text, and a merged value whole', function () {
    $drawn = variablesService()->render(
        variablesVersion('<p>Offer {{ submission.price }}</p><ul><li>One</li><li>Two</li></ul><p>See <a href="https://acme.test/x">the offer</a>.</p>'),
        ['submission' => ['price' => 'under <500 EGP & more']],
    );
    $manual = variablesService()->render(
        variablesVersion('<p>x</p>', 'Offer {{ submission.price }}', EmailTemplateVersion::PLAIN_MANUAL),
        ['submission' => ['price' => 'under <500 EGP']],
    );

    expect($drawn['plain'])->toBe("Offer under <500 EGP & more\n\n- One\n- Two\n\nSee the offer (https://acme.test/x).")
        ->and($drawn['html'])->toContain('under &lt;500 EGP &amp; more')
        ->and($manual['plain'])->toBe('Offer under <500 EGP');
});

it('takes the From address as optional, and refuses one that is not an address or a name with a line break', function () {
    expect(variablesService()->validateDraft(variablesVersion('<p>x</p>')))->toBe([])
        ->and(variablesService()->validateDraft(variablesVersion('<p>x</p>', from: 'sales@example.com', fromName: 'Sales')))->toBe([])
        ->and(variablesService()->validateDraft(variablesVersion('<p>x</p>', from: 'not-an-address')))
        ->toBe(['fromAddress' => 'The From address is not a valid email address: not-an-address'])
        ->and(variablesService()->validateDraft(variablesVersion('<p>x</p>', fromName: "Sales\nBcc: x@example.com")))
        ->toBe(['fromName' => 'The From name cannot contain a line break.']);
});
