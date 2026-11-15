<?php

/**
 * A default template put back as it was shipped is written in the site's language, as it was
 * installed, whatever language the administrator who resets it reads the admin in (spec 120,
 * FR-019 and FR-022; DECISIONS #362). Its words go out in every email the site sends with it.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Tests\Support\AdministratorsRequest;

it('FR-022: puts a default back in the site\'s language, not the language of whoever resets it', function (
    string $siteLanguage,
    string $profileLanguage,
    string $subject,
) {
    $request = AdministratorsRequest::on($siteLanguage, $profileLanguage)->run(<<<'PHP'
        $templates = $container->make(\Corex\Email\Studio\EmailTemplateRepository::class);
        $store = $container->make(\Corex\Email\Studio\WpEmailStudioStore::class);
        $template = $templates->findBySlug('contact-confirmation');
        if ($template === null) {
            return ['subject' => null];
        }
        // The site's template, as it was: the version the reset makes is deleted with the request.
        $record = $store->find($template->id);
        try {
            $version = $container->make(\Corex\Email\Studio\DefaultEmailTemplates::class)
                ->reset($template->id, 1, new \DateTimeImmutable('now'));

            return ['subject' => $version->subject];
        } finally {
            $store->update($record['id'], $record['name'], $record['payload']);
        }
        PHP);

    expect($request['drawn']['subject'])->toBe($subject);
})->with([
    'an English site, an administrator who reads the admin in Arabic' => ['en_US', 'ar', 'Thanks, we have your message'],
    'an Arabic site, an administrator who reads the admin in English' => ['ar', 'en_US', '[ar] Thanks, we have your message'],
]);
