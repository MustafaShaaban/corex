<?php

/**
 * The Email Studio controller the container builds can preview a template and put a default back
 * (spec 120, FR-001 and FR-022): asked through WordPress's REST server, as the screen asks.
 *
 * The provider builds the controller by hand (spec 117 gives it the ability checks); a dependency
 * it is not handed is null, and the preview and the reset then answer that they are not there.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\WpEmailStudioStore;
use Corex\Tests\Support\CreatedPosts;

beforeEach(function () {
    $this->container = Corex\Boot::app()->container();
    $this->posts = CreatedPosts::watch('corex_email_asset');
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->template = $this->container->make(EmailTemplateRepository::class)->findBySlug('contact-confirmation');
    if ($this->template === null) {
        $this->markTestSkipped('This site has no "Contact confirmation" template.');
    }
    $this->record = $this->container->make(WpEmailStudioStore::class)->find($this->template->id);
});

afterEach(function () {
    if (isset($this->record)) {
        $this->container->make(WpEmailStudioStore::class)->update($this->record['id'], $this->record['name'], $this->record['payload']);
    }
    $this->posts->delete();
    wp_set_current_user(0);
});

function studioRoute(string $route, array $body = []): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/corex/v1/email-studio' . $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params($body);

    return rest_do_request($request);
}

it('FR-001: previews a template through the container\'s controller', function () {
    $templates = $this->container->make(EmailTemplateRepository::class);
    $version = $templates->findVersion($this->template->id, $this->template->draftVersion);

    $response = studioRoute('/templates/' . $this->template->id . '/preview', [
        'subject' => $version->subject,
        'from_name' => '',
        'from_address' => '',
        'html_body' => $version->htmlBody,
        'plain_text' => '',
        'plain_text_mode' => 'auto',
        'layout_id' => $version->layoutId,
        'layout_version' => $version->layoutVersion,
    ]);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['html'] ?? '')->toContain('This message was sent by');
});

it('FR-022: puts a default back through the container\'s controller', function () {
    $response = studioRoute('/templates/' . $this->template->id . '/reset');

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['version']['subject'] ?? null)->not->toBeNull();
});
