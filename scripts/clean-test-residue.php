<?php

/**
 * Removes what test runs left on a development site (spec 116, US-NFR-09).
 *
 *     wp eval-file scripts/clean-test-residue.php        # lists what it would remove, then asks
 *     wp eval-file scripts/clean-test-residue.php yes    # removes without asking
 *
 * It removes the submissions that carry a test's marker: a flow whose slug contains `e2e`, a
 * `utm_source` of `playwright`, or a sender at `example.com` or `example.test`, the domains kept
 * for examples, which no person writes from (the same markers as tests/e2e/residue.js, which does
 * this at the start and end of every browser run). It lists them by form first, and removes
 * nothing until it is told to.
 *
 * It refuses a site whose CoreX operations mode is declared Production, or that sets
 * WP_ENVIRONMENT_TYPE to production. Development only: `scripts/` is not in the shared-host
 * package.
 *
 * No strict_types declaration: WP-CLI's eval-file evaluates the file, where one is a fatal error.
 *
 * @package Corex\Scripts
 */

if (! defined('WP_CLI') || ! WP_CLI) {
    exit(1);
}

$declared = (string) get_option('corex_operations_mode', '');
$environment = defined('WP_ENVIRONMENT_TYPE') ? (string) WP_ENVIRONMENT_TYPE : (string) getenv('WP_ENVIRONMENT_TYPE');

if ($declared === 'production' || $environment === 'production') {
    WP_CLI::error(sprintf(
        'This is a production site (%s). Test residue is removed from development sites only; nothing was removed.',
        $declared === 'production' ? 'its CoreX operations mode is Production' : 'WP_ENVIRONMENT_TYPE is production',
    ));
}

global $wpdb;

$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT p.ID, COALESCE(slug.meta_value, '') AS form FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} slug ON slug.post_id = p.ID AND slug.meta_key = 'corex_form_slug'
    LEFT JOIN {$wpdb->postmeta} utm ON utm.post_id = p.ID AND utm.meta_key = 'corex_utm_json'
    LEFT JOIN {$wpdb->postmeta} sender ON sender.post_id = p.ID AND sender.meta_key IN ('corex_submitter_email', 'corex_field_email')
    WHERE p.post_type = 'corex_submission' AND (
        slug.meta_value LIKE %s OR utm.meta_value LIKE %s OR sender.meta_value LIKE %s OR sender.meta_value LIKE %s
    )",
    '%e2e%',
    '%playwright%',
    '%@example.com',
    '%@example.test',
));

$ids = array_values(array_unique(array_map(static fn ($row): int => (int) $row->ID, $rows)));
$byForm = [];

foreach ($rows as $row) {
    $byForm[$row->form === '' ? '(no form)' : $row->form][(int) $row->ID] = true;
}

if ($ids === []) {
    WP_CLI::success(sprintf('%s holds no test residue.', home_url()));
    return;
}

WP_CLI::log(sprintf('%s holds %d submission(s) that test runs left:', home_url(), count($ids)));

foreach ($byForm as $form => $members) {
    WP_CLI::log(sprintf('  %5d  %s', count($members), $form));
}

if (! in_array('yes', $args ?? [], true)) {
    WP_CLI::confirm(sprintf('Remove these %d submission(s) for good?', count($ids)));
}

foreach ($ids as $id) {
    wp_delete_post($id, true);
}

WP_CLI::success(sprintf('Removed %d submission(s).', count($ids)));
