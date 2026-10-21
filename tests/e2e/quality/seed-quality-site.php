<?php

/**
 * Prepares a site for the quality checks (spec 116): `wp eval-file tests/e2e/quality/seed-quality-site.php`.
 *
 * 1. Applies the Company kit at Full, through the setup wizard's own route, as the first
 *    administrator: the request the wizard's Apply button sends, with its confirmation.
 * 2. Gives each theme template the kit does not reach a page that reaches it: `page-account`,
 *    `page-form` and `page-landing` are chosen by a page's slug, `single-corex_project` needs a
 *    project, and `index` renders the posts page, which the kit leaves unset (US-KIT-04).
 *
 * Run once, on CI's WordPress or a scratch site. It refuses a site whose CoreX operations mode is
 * declared `production`, since it creates and changes pages. A second run applies the kit again
 * and leaves the extra pages as they are.
 *
 * No strict_types declaration: WP-CLI's eval-file evaluates the file, where one is a fatal error.
 *
 * @package Corex\Tests\E2E
 */

if (get_option('corex_operations_mode') === 'production') {
    fwrite(STDERR, "This site is in production mode; it is not prepared for the quality checks.\n");
    exit(1);
}

$administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
wp_set_current_user((int) ($administrators[0] ?? 0));

$request = new WP_REST_Request('POST', '/corex/v1/setup/apply');
$request->set_param('kit', 'company');
$request->set_param('level', 'full');
$request->set_param('confirm', true);
// The route asks for the REST nonce the admin screen sends; it is made for the user set above.
$request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

$response = rest_do_request($request);

if ($response->get_status() !== 200) {
    fwrite(STDERR, sprintf("The Company kit was not applied: HTTP %d %s\n", $response->get_status(), wp_json_encode($response->get_data())));
    exit(1);
}

fwrite(STDOUT, 'Company kit applied at Full: ' . wp_json_encode($response->get_data()) . "\n");

$pages = [
    'account' => ['Account', '<!-- wp:corex/account /-->'],
    'form'    => ['Form', '<!-- wp:corex/form /-->'],
    'landing' => ['Landing', '<!-- wp:paragraph --><p>A landing page for the quality checks.</p><!-- /wp:paragraph -->'],
];

foreach ($pages as $slug => [$title, $content]) {
    if (get_page_by_path($slug) instanceof WP_Post) {
        continue;
    }

    wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content]);
    fwrite(STDOUT, "Page /{$slug}/ created.\n");
}

if (post_type_exists('corex_project') && get_posts(['post_type' => 'corex_project', 'numberposts' => 1, 'fields' => 'ids']) === []) {
    wp_insert_post([
        'post_type'    => 'corex_project',
        'post_status'  => 'publish',
        'post_name'    => 'quality-project',
        'post_title'   => 'A project',
        'post_content' => '<!-- wp:paragraph --><p>A project for the quality checks.</p><!-- /wp:paragraph -->',
    ]);
    fwrite(STDOUT, "Project /projects/quality-project/ created.\n");
}

// A subscriber, for the refused state of every screen (states.spec.js). A test account on a test
// site, with the password the spec signs in with.
if (! get_user_by('login', 'corex-quality-subscriber')) {
    wp_insert_user([
        'user_login' => 'corex-quality-subscriber',
        'user_email' => 'quality-subscriber@example.test',
        'user_pass'  => 'CorexQuality!subscriber1',
        'role'       => 'subscriber',
    ]);
    fwrite(STDOUT, "Subscriber corex-quality-subscriber created.\n");
}

$blog = get_page_by_path('blog');

if ($blog instanceof WP_Post && (int) get_option('page_for_posts') === 0) {
    update_option('page_for_posts', $blog->ID);
    fwrite(STDOUT, "The Blog page is the posts page.\n");
}

flush_rewrite_rules(false);
