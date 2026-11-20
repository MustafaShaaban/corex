<?php

/**
 * Applies the Company kit at Minimal to a new scratch site, for the theme's preview (spec 134, FR-026):
 *
 *     wp --user=<administrator> --path=<WordPress folder> eval-file scripts/theme-preview-site.php
 *
 * The same request the Setup Wizard's Apply button sends, with its confirmation: the kit makes its
 * pages, keeps any page that already holds content, and sets its Home as the front page. Then
 * `npm run theme:preview` takes the capture (theme/README.md, "The preview image").
 *
 * It refuses a site CoreX does not read as development, since it creates pages and changes the
 * front page.
 *
 * No strict_types declaration: WP-CLI's eval-file evaluates the file, where one is a fatal error.
 *
 * @package Corex\Scripts
 */

defined('ABSPATH') || exit;

$environment = \Corex\Boot::app()->container()->make(\Corex\Environment\SiteEnvironment::class)->current();

if (! $environment->isDevelopment()) {
    fwrite(STDERR, "CoreX reads this site as {$environment->environment}; the Company kit is applied for the preview on a development scratch site only.\n");
    exit(1);
}

if (! current_user_can('manage_options')) {
    fwrite(STDERR, "Run it as an administrator: wp --user=<administrator> eval-file scripts/theme-preview-site.php\n");
    exit(1);
}

$request = new WP_REST_Request('POST', '/corex/v1/setup/apply');
$request->set_param('kit', 'company');
$request->set_param('level', 'minimal');
$request->set_param('confirm', true);
// The route asks for the REST nonce the admin screen sends; it is made for the user WP-CLI runs as.
$request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

$response = rest_do_request($request);

if ($response->get_status() !== 200) {
    fwrite(STDERR, sprintf("The Company kit was not applied: HTTP %d %s\n", $response->get_status(), wp_json_encode($response->get_data())));
    exit(1);
}

fwrite(STDOUT, 'The Company kit is applied at Minimal: ' . wp_json_encode($response->get_data()) . "\n");
