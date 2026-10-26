<?php

/**
 * A flow action, an email variable and a routing target registered through the documented
 * WordPress hooks run, as the corex-forms README shows them (spec 122: US-FLW-07, US-FLW-04).
 *
 * The hooks are read on `init`, and Email Studio reads its variable list once, so the extensions
 * are registered where a plugin registers them: before WordPress loads, in a PHP process of its
 * own that boots the install, submits, and reports what happened.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

/**
 * Runs the site with the README's three extensions in a fresh PHP process and returns its report.
 *
 * @return array<string,mixed>
 */
function runWithFlowExtensions(array $members): array
{
    $script = tempnam(sys_get_temp_dir(), 'corex-flow-hooks-') . '.php';
    file_put_contents($script, sprintf(<<<'PHP'
        <?php
        $_SERVER['HTTP_HOST'] = %1$s;
        $_SERVER['REQUEST_URI'] = '/';
        $members = %2$s;
        $report = ['actions' => []];

        // The README's examples, registered as a plugin does: before `init` reads the hooks.
        // A capture-only site: nothing leaves (spec 113).
        $GLOBALS['wp_filter'] = [
            'pre_option_corex_app_env' => [10 => [['function' => static fn () => 'development', 'accepted_args' => 0]]],
            'corex_flow_actions' => [10 => [['accepted_args' => 1, 'function' => static function (array $actions) use (&$report): array {
                $actions['readme_crm_push'] = [
                    'label' => 'Send to the CRM',
                    'callback' => static function (array $submission) use (&$report): void {
                        $report['actions'][] = ['is_test' => $submission['is_test'], 'test_notice' => $submission['test_notice'], 'email' => $submission['values']['email'] ?? ''];
                    },
                ];
                $actions['readme_failing'] = [
                    'label' => 'Always fails',
                    'callback' => static function (): void {
                        throw new RuntimeException('The CRM is down.');
                    },
                ];

                return $actions;
            }]]],
            'corex_flow_email_variables' => [10 => [['accepted_args' => 1, 'function' => static function (array $variables): array {
                $variables['crm.id'] = ['label' => 'CRM record', 'callback' => static fn (array $context): string => 'CRM-' . strlen((string) ($context['submission']['email'] ?? ''))];

                return $variables;
            }]]],
            'corex_flow_routing_targets' => [10 => [['accepted_args' => 1, 'function' => static function (array $targets): array {
                $targets['readme_by_desk'] = [
                    'label' => 'The desk team',
                    'callback' => static fn (array $submission): array => ['type' => 'team', 'value' => 'readme-desk'],
                ];

                return $targets;
            }]]],
        ];

        define('WP_USE_THEMES', false);
        require %3$s;

        $created = [];
        add_action('wp_insert_post', static function (int $id) use (&$created): void {
            $created[] = $id;
        });
        $teams = get_option('corex_flow_teams', null);
        $container = \Corex\Boot::app()->container();
        $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
        wp_set_current_user((int) $admins[0]);
        $request = static function (string $method, string $route, array $payload, array $params = []): WP_REST_Request {
            $request = new WP_REST_Request($method, $route);
            $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
            $request->set_header('Content-Type', 'application/json');
            $request->set_body((string) wp_json_encode($payload));
            $request->set_url_params($params);

            return $request;
        };

        try {
            update_option('corex_flow_teams', [['key' => 'readme-desk', 'name' => 'Desk', 'members' => $members]]);
            $now = new DateTimeImmutable();
            $templates = new \Corex\Email\Studio\EmailTemplateRepository($container->make(\Corex\Email\Studio\WpEmailStudioStore::class));
            $template = $templates->create('readme-hooks', 'README hooks', get_current_user_id(), $now);
            $version = $templates->saveDraft(new \Corex\Email\Studio\EmailTemplateVersion(
                id: 1, templateId: $template->id, versionNumber: 1,
                subject: 'README hooks', fromName: 'CoreX', fromAddress: 'hello@example.com',
                htmlBody: '<p>Record {{ crm.id }}</p>', plainText: 'Record {{ crm.id }}', plainTextMode: 'manual',
                layoutId: 1, layoutVersion: 1, variableKeys: ['crm.id'],
                createdBy: get_current_user_id(), createdAt: $now, checksum: hash('sha256', 'readme-hooks'),
            ));
            $templates->activate($template->id, $version->versionNumber, get_current_user_id(), $now);

            $flows = $container->make(\Corex\Forms\Flow\FlowController::class);
            $created_flow = $flows->create($request('POST', '/corex/v1/flows', [
                'slug' => 'readme-hooks',
                'name' => 'README hooks',
                'owner_id' => get_current_user_id(),
                'configuration' => [
                    'schema' => [['uuid' => 'f-email', 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
                    'validation' => ['email' => ['required', 'email']],
                    'routing' => ['rules' => [], 'fallback' => ['type' => 'extension', 'config' => ['value' => 'readme_by_desk']]],
                    'email_routes' => [[
                        'event' => 'team_notification', 'enabled' => true,
                        'template_id' => $template->id, 'recipient' => 'routed_owner', 'reply_to' => '',
                    ]],
                    'success' => ['type' => 'inline', 'message' => 'Received.'],
                    'placement_snapshot' => ['type' => 'block'],
                    'actions' => ['readme_crm_push', 'readme_failing'],
                ],
            ]));
            $flowId = (int) $created_flow->get_data()['data']['flow']['id'];
            $report['published'] = $flows->publish($request('POST', "/corex/v1/flows/{$flowId}/publish", ['expected_version' => 1], ['id' => $flowId]))->get_status();
            $report['catalog'] = $flows->extensions($request('GET', '/corex/v1/flows/extensions', []))->get_data()['data'];
            $report['schema_has_variable'] = array_key_exists('crm.id', apply_filters('corex_email_variable_schema', []));

            wp_set_current_user(0);
            $submitted = $container->make(\Corex\Forms\Submission\FlowSubmissionController::class)
                ->submit($request('POST', "/corex/v1/flows/{$flowId}/submit", ['email' => 'visitor@example.test'], ['id' => $flowId]));
            $submissionId = (int) ($submitted->get_data()['data']['submission_id'] ?? 0);
            $report['submitted'] = $submitted->get_status();
            $report['owner'] = [get_post_meta($submissionId, 'corex_owner_type', true), get_post_meta($submissionId, 'corex_owner_key', true)];
            $report['timeline'] = get_post_meta($submissionId, 'corex_submission_timeline', true);
            $report['captures'] = array_map(
                static fn ($capture): array => ['to' => $capture->to, 'body' => $capture->body, 'subject' => $capture->subject],
                array_values(array_filter(
                    (new \Corex\Email\Capture\CapturedEmailRepository($container->make(\Corex\Email\Studio\WpEmailStudioStore::class)))->latest(),
                    static fn ($capture): bool => in_array($capture->id, $created, true),
                )),
            );

            wp_set_current_user((int) $admins[0]);
            $tested = $flows->test($request('POST', "/corex/v1/flows/{$flowId}/test", ['expected_version' => 1, 'values' => ['email' => 'tester@example.test']], ['id' => $flowId]));
            $report['tested'] = $tested->get_data()['data']['completed'] ?? false;
        } finally {
            foreach (array_unique($created) as $id) {
                wp_delete_post($id, true);
            }
            if ($teams === null) {
                delete_option('corex_flow_teams');
            } else {
                update_option('corex_flow_teams', $teams);
            }
        }

        echo 'COREX_HOOKS_JSON:' . wp_json_encode($report);
        PHP,
        var_export((string) wp_parse_url(home_url(), PHP_URL_HOST) . (wp_parse_url(home_url(), PHP_URL_PORT) ? ':' . wp_parse_url(home_url(), PHP_URL_PORT) : ''), true),
        var_export($members, true),
        var_export(ABSPATH . 'wp-load.php', true),
    ));

    try {
        $process = proc_open([PHP_BINARY, '-d', 'memory_limit=512M', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    } finally {
        unlink($script);
    }

    $marker = strrpos($stdout, 'COREX_HOOKS_JSON:');
    if ($marker === false) {
        throw new RuntimeException("The site with the extensions did not report.\n" . $stdout . $stderr);
    }

    return (array) json_decode(substr($stdout, $marker + strlen('COREX_HOOKS_JSON:')), true);
}

beforeEach(function () {
    $this->users = [];
    foreach (['desk-one', 'desk-two'] as $name) {
        $this->users[] = wp_insert_user([
            'user_login' => 'readme-' . $name . '-' . wp_generate_password(6, false),
            'user_pass' => wp_generate_password(),
            'user_email' => $name . '-' . wp_generate_password(6, false) . '@example.test',
            'role' => 'subscriber',
        ]);
    }
});

afterEach(function () {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($this->users as $userId) {
        wp_delete_user($userId);
    }
});

it('runs the README extensions: the action, the email variable and the routing target', function () {
    $report = runWithFlowExtensions($this->users);
    $actions = array_values(array_filter($report['timeline'], static fn (array $event): bool => $event['stage'] === 'action'));
    $addresses = array_map(static fn (int $id): string => get_userdata($id)->user_email, $this->users);

    expect($report['published'])->toBe(200)
        ->and($report['submitted'])->toBe(200)
        ->and($report['schema_has_variable'])->toBeTrue()
        ->and(array_column($report['catalog']['flow_actions'], 'key'))->toContain('readme_crm_push', 'readme_failing')
        ->and(array_column($report['catalog']['routing_targets'], 'label'))->toContain('The desk team')
        // The routing target: the team it returned.
        ->and($report['owner'])->toBe(['team', 'readme-desk'])
        // Routed owner: every member of that team, with the variable filled in.
        ->and($report['captures'])->toHaveCount(1)
        ->and($report['captures'][0]['to'])->toEqualCanonicalizing($addresses)
        ->and($report['captures'][0]['body'])->toContain('Record CRM-20')
        // The actions: each ran once, one failed without stopping anything.
        ->and(array_column($actions, 'outcome'))->toBe(['success', 'failure'])
        ->and($actions[1]['summary']['message'])->toBe('Always fails failed: The CRM is down.')
        ->and($report['actions'][0])->toBe(['is_test' => false, 'test_notice' => '', 'email' => 'visitor@example.test'])
        // A marked test runs the actions, told so.
        ->and($report['tested'])->toBeTrue()
        ->and($report['actions'][1])->toBe(['is_test' => true, 'test_notice' => 'This is a test.', 'email' => 'tester@example.test']);
});
