<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use DomainException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Thin REST boundary for the complete permission-scoped Submissions Inbox.
 */
final readonly class SubmissionsController
{
    public function __construct(
        private SubmissionControllerServices $services,
        private SubmissionRestGateway $gateway,
    ) {
    }

    public function register(): void
    {
        $this->route('/submissions', 'GET', 'index');
        $this->route('/submissions', 'POST', 'createExport');
        $this->route('/submissions/(?P<id>\d+)', 'GET', 'show');
        $this->route('/submissions/(?P<id>\d+)', 'PATCH', 'update');
        $this->route('/submissions/(?P<id>\d+)/notes', 'POST', 'addNote');
        $this->route('/submissions/(?P<id>\d+)/reply', 'POST', 'reply');
        $this->route('/submissions/(?P<id>\d+)/resend', 'POST', 'resend');
        $this->route('/submissions/(?P<id>\d+)/email-log', 'GET', 'emailLog');
        $this->route('/submissions/bulk/preview', 'POST', 'bulkPreview');
        $this->route('/submissions/bulk/apply', 'POST', 'bulkApply');
        $this->route('/submissions/exports', 'GET', 'exports');
        $this->route('/submissions/exports', 'POST', 'createExport');
        $this->route('/submissions/exports/(?P<export>\d+)/download', 'GET', 'downloadExport');
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, fn (): Response => Response::ok(
            $this->services->queries->query(get_current_user_id(), SubmissionInboxQuery::from($request->get_params())),
        ));
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, function () use ($request): Response {
            $record = $this->services->queries->detail(get_current_user_id(), absint($request->get_param('id')));
            if ($record === null) {
                throw new DomainException(__('Submission was not found.', 'corex'));
            }

            return Response::ok(['submission' => $record]);
        });
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->updateShape(), function (Request $safe) use ($request): Response {
            $scope = $this->scope();
            $id = absint($request->get_param('id'));
            $expected = (string) ($safe->input['expected_updated_at'] ?? '');
            $record = null;
            if (($safe->input['status'] ?? '') !== '') {
                $record = $this->services->workflow->changeStatus($scope, $id, (string) $safe->input['status'], $expected);
                $expected = (string) $record['updated_at'];
            }
            if ((bool) ($safe->input['mark_read'] ?? false)) {
                $record = $this->services->workflow->markRead($scope, $id, $expected);
                $expected = (string) $record['updated_at'];
            }
            if (($safe->input['owner_type'] ?? '') !== '') {
                $record = $this->services->workflow->assign($scope, $id, new SubmissionAssignment(
                    (string) $safe->input['owner_type'],
                    (string) ($safe->input['owner_key'] ?? ''),
                ), $expected);
            }
            if ($record === null) {
                throw new DomainException(__('No submission workflow change was requested.', 'corex'));
            }

            return Response::ok(['submission' => $record]);
        });
    }

    public function addNote(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, [
            'body' => 'sanitize_textarea_field',
            'visibility' => 'sanitize_key',
        ], fn (Request $safe): Response => Response::ok(['note' => $this->services->workflow->addNote(
            $this->scope(),
            absint($request->get_param('id')),
            (string) ($safe->input['body'] ?? ''),
            (string) ($safe->input['visibility'] ?? 'corex-team'),
        )]));
    }

    public function reply(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, [
            'subject' => 'sanitize_text_field',
            'body' => 'wp_kses_post',
        ], fn (Request $safe): Response => Response::ok(['result' => $this->services->email->reply(
            $this->scope(),
            absint($request->get_param('id')),
            new SubmissionReply((string) ($safe->input['subject'] ?? ''), (string) ($safe->input['body'] ?? '')),
        )->toArray()]));
    }

    public function resend(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, ['attempt_id' => 'sanitize_text_field'], fn (Request $safe): Response =>
            Response::ok(['result' => $this->services->email->resend(
                $this->scope(),
                absint($request->get_param('id')),
                (string) ($safe->input['attempt_id'] ?? ''),
            )->toArray()]));
    }

    public function emailLog(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, fn (): Response => Response::ok(['log' => $this->services->email->log(
            $this->scope(),
            absint($request->get_param('id')),
            sanitize_text_field((string) $request->get_param('attempt_id')),
        )]));
    }

    public function bulkPreview(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->bulkShape(), fn (Request $safe): Response => Response::ok([
            'preview' => $this->previewArray($this->services->bulk->preview(
                $this->scope(),
                (string) ($safe->input['action'] ?? ''),
                (array) ($safe->input['submission_ids'] ?? []),
                (array) ($safe->input['parameters'] ?? []),
            )),
        ]));
    }

    public function bulkApply(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, ['token' => 'sanitize_text_field'], fn (Request $safe): Response =>
            Response::ok(['result' => $this->services->bulk->apply(
                $this->scope(),
                (string) ($safe->input['token'] ?? ''),
            )]));
    }

    public function createExport(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->exportShape(), fn (Request $safe): Response => Response::ok([
            'export' => $this->services->exports->request(
                $this->scope(),
                SubmissionExportRequest::from($safe->input),
            )->toArray(),
        ]));
    }

    public function exports(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, fn (): Response => Response::ok(['exports' => array_map(
            static fn (SubmissionExportRun $run): array => $run->toArray(),
            $this->services->exports->history($this->scope()),
        )]));
    }

    public function downloadExport(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, fn (): Response => Response::ok([
            'artifact' => $this->services->exports->download($this->scope(), absint($request->get_param('export'))),
        ]));
    }

    private function scope(): SubmissionAccessScope
    {
        return $this->services->access->scopeFor(get_current_user_id())
            ?? throw new DomainException(__('You cannot manage submissions.', 'corex'));
    }

    /** @return array<string,callable|string> */
    private function updateShape(): array
    {
        return [
            'status' => 'sanitize_key',
            'mark_read' => 'rest_sanitize_boolean',
            'owner_type' => 'sanitize_key',
            'owner_key' => 'sanitize_key',
            'expected_updated_at' => 'sanitize_text_field',
        ];
    }

    /** @return array<string,callable|string> */
    private function bulkShape(): array
    {
        return [
            'action' => 'sanitize_key',
            'submission_ids' => $this->integerList(...),
            'parameters' => $this->parameters(...),
        ];
    }

    /** @return array<string,callable|string> */
    private function exportShape(): array
    {
        return [
            'scope' => 'sanitize_key',
            'selected_ids' => $this->integerList(...),
            'columns' => $this->keyList(...),
            'query' => $this->query(...),
            'include_test' => 'rest_sanitize_boolean',
            'personal_data_acknowledged' => 'rest_sanitize_boolean',
        ];
    }

    /** @return list<int> */
    private function integerList(mixed $value): array
    {
        return array_values(array_filter(array_map('absint', is_array($value) ? $value : [])));
    }

    /** @return list<string> */
    private function keyList(mixed $value): array
    {
        return array_values(array_filter(array_map('sanitize_key', is_array($value) ? $value : [])));
    }

    /** @return array<string,string> */
    private function parameters(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'owner_type' => sanitize_key((string) ($value['owner_type'] ?? '')),
            'owner_key' => sanitize_key((string) ($value['owner_key'] ?? '')),
        ];
    }

    /** @return array<string,mixed> */
    private function query(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'search' => sanitize_text_field((string) ($value['search'] ?? '')),
            'flow' => absint($value['flow'] ?? 0),
            'status' => sanitize_key((string) ($value['status'] ?? '')),
            'owner' => sanitize_text_field((string) ($value['owner'] ?? '')),
            'date_from' => sanitize_text_field((string) ($value['date_from'] ?? '')),
            'date_to' => sanitize_text_field((string) ($value['date_to'] ?? '')),
        ];
    }

    /** @return array<string,mixed> */
    private function previewArray(SubmissionBulkPreview $preview): array
    {
        return [
            'token' => $preview->token,
            'action' => $preview->action,
            'submission_ids' => $preview->submissionIds,
            'parameters' => $preview->parameters,
            'count' => $preview->count(),
            'expires_at' => $preview->expiresAt,
        ];
    }

    private function route(string $path, string $method, string $callback): void
    {
        register_rest_route('corex/v1', $path, [
            'methods' => $method,
            'callback' => [$this, $callback],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => ['sanitize_callback' => 'absint'],
                'export' => ['sanitize_callback' => 'absint'],
            ],
        ]);
    }
}
