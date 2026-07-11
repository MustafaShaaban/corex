# Data Model: CoreX Product Functional Completion

This document defines domain shapes and state transitions. Persistence is owned by repositories; request controllers do not read or write storage directly.

## Shared Value Objects

### Actor Reference

- `user_id`: positive user ID or `0` for a system process
- `display_name`: snapshot used for durable history
- `kind`: `user`, `system`, `cli`, or `cron`
- `ip_hash`: optional short-lived pseudonymous security context; never a raw analytics identifier

### Operation Result

- `operation_id`: stable unique identifier
- `state`: `accepted`, `completed`, `partial`, `failed`, `cancelled`, or `blocked`
- `message`: safe operator-facing summary
- `errors`: structured safe error codes/messages
- `affected_ids`: bounded list or reference to a result artifact
- `started_at`, `finished_at`
- `audit_event_id`

### Confirmation

- `operation_kind`
- `target_hash`: binds confirmation to the previewed target set and values
- `actor_id`
- `expires_at`
- `required_phrase`: optional typed confirmation such as `PRODUCTION`
- `used_at`: single-use replay protection

## Activity and Audit

### Activity Event

- `id`: monotonic event ID
- `event_uuid`: globally unique event identifier
- `occurred_at`: UTC timestamp
- `actor_id`, `actor_kind`, `actor_label`
- `area`: overview, addons, forms, submissions, data, data-models, email, blog, insights, setup, settings, operations, security, access, theme, docs
- `kind`: registered event kind
- `target_type`, `target_id`, `target_label`
- `outcome`: success, warning, failure, denied, captured, queued, sent, reverted
- `summary`: translation-ready event summary data, not pre-escaped HTML
- `context_json`: sanitized structured context; secrets and full personal payloads forbidden
- `sensitivity`: public-admin, restricted, personal, security
- `retention_until`

**Relationships**: domain records may reference an activity event; activity never owns domain state.

**Lifecycle**: append-only → retained → pruned/anonymized by policy. Events are never edited to rewrite history.

## Abilities and Access

### Ability Definition

- `key`: unique `corex_*` capability key
- `label`, `description`
- `group`: one of the product areas in FR-085
- `risk`: normal, sensitive, dangerous, critical
- `locked`: code/config lock state
- `implies`: abilities automatically satisfied by this ability
- `screen_slugs`, `action_keys`

### Role Ability Grant

- `role_key`, `ability_key`
- `effect`: allow or deny
- `source`: explicit, inherited, code, config, compatibility
- `updated_by`, `updated_at`

**Identity**: unique `(role_key, ability_key, source)`.

### Access Request

- `id`
- `requester_id`
- `ability_key` or `area_key`
- `reason`
- `state`: pending, approved, denied, cancelled, expired
- `reviewer_id`, `review_note`, `reviewed_at`
- `notification_attempt_id`
- `created_at`, `expires_at`

**Transitions**: pending → approved | denied | cancelled | expired. Terminal decisions cannot be edited; a new request is created instead.

### Access Grant Operation

- `id`, `actor_id`
- `subject_type`: user or role
- `subject_key`
- `ability_keys`
- `old_effects`, `new_effects`
- `confirmation_hash`
- `result_state`, `created_at`

**Constraints**: cannot remove the actor's required critical abilities; cannot leave zero full-access administrators.

## Add-ons

### Add-on Descriptor

- `slug`, `name`, `version`, `description`
- `edition`: core, free, optional, commercial metadata only when real
- `package_state`: installed, missing, invalid
- `runtime_state`: active, inactive, dependency-blocked, error
- `dependencies`: CoreX and external dependency descriptors
- `registrations`: blocks, routes, services, data sources, commands, pages
- `docs_url`, `logo_asset`
- `update_state`: current, update-available, not-tracked, error
- `update_version`: nullable

## Flows and Forms

### Flow

- `id`, `uuid`, `slug`, `name`, `description`
- `state`: draft, published, closed, expired
- `owner_id`
- `placement_type`: none, page, post, block, registered
- `placement_id`
- `current_draft_version`, `published_version`
- `test_mode`
- `created_by`, `updated_by`, `created_at`, `updated_at`, `published_at`, `closed_at`, `expires_at`

**Identity**: `uuid` immutable; `slug` unique among non-trashed flows.

**Transitions**:

- draft → published
- published → draft (unpublish) | closed | expired
- closed → draft
- expired → draft after expiry is changed

Publishing creates an immutable `Flow Version`.

### Flow Version

- `id`, `flow_id`, `version_number`
- `schema_json`
- `validation_json`
- `routing_json`
- `email_routes_json`
- `success_json`
- `placement_snapshot_json`
- `created_by`, `created_at`
- `checksum`

**Identity**: unique `(flow_id, version_number)` and checksum of canonical configuration.

### Field Definition

- `uuid`: stable within a flow across draft reordering
- `key`: unique within a version
- `label`, `type`, `placeholder`, `help_text`
- `default_value`
- `required`
- `options`: ordered value/label pairs
- `validation_rules`: ordered rule definitions
- `position`
- `visibility`, `width`, `step_key`
- `personal_data_class`: none, contact, identity, consent, sensitive, custom
- `extension_config`

### Validation Rule

- `rule_key`
- `parameters`
- `message`
- `source`: built-in or extension

### Routing Rule

- `uuid`, `position`
- `condition`: field/operator/value or always
- `target_type`: email, user, role, team, page_owner, post_author, flow_owner, field_value, extension
- `target_config`
- `stop_on_match`: always true for built-in first-match semantics

### Email Binding

- `event`: submitter_confirmation, team_notification, admin_failure, extension
- `template_id`, `template_version`
- `recipient_rules`
- `reply_to_rule`
- `enabled`

### Success Definition

- `kind`: inline, page_redirect, url_redirect, extension
- `message`, `target`, `extension_config`

## Submissions

### Submission

- `id`, `uuid`
- `flow_id`, `flow_version_id`, `flow_label_snapshot`
- `is_test`
- `status`: new, in_progress, replied, closed, spam, archived
- `read_at`, `read_by`
- `owner_type`: none, user, role, team
- `owner_key`
- `submitter_name`, `submitter_email` projections where authorized
- `values_json`: sanitized submitted values keyed by version field UUID/key
- `hidden_metadata_json`
- `utm_json`
- `consent_snapshot_json`
- `spam_json`: checks, scores, provider result
- `retention_state`: active, due, archived, trashed, anonymized, held
- `exported_at`
- `created_at`, `updated_at`

### Submission Note

- `id`, `submission_id`, `author_id`
- `body`
- `visibility`: corex-team or restricted
- `created_at`, `updated_at`

### Submission Timeline Event

- `id`, `submission_id`, `activity_event_id`
- `stage`: received, validated, spam, stored, routed, email, status, assignment, note, export, retention
- `outcome`, `summary_json`, `created_at`

### Submission Export

- `export_run_id`
- `scope`: accessible, filtered, selected
- `query_json`, `selected_ids`
- `columns`
- `include_test`
- `format`
- `personal_data_classes`

## Data Sources and Models

### Source Capability Set

- `source_key`
- `read`, `query`, `schema`, `detail`
- `create`, `update`, `delete`, `bulk_update`, `bulk_delete`
- `import_dry_run`, `import_commit`
- `export_csv`, `export_xlsx`
- `migrations`, `rollback`
- `max_page_size`
- `permission_map`

### Source Field

- `key`, `label`, `type`
- `required`, `nullable`, `read_only`
- `filter_operators`, `sortable`
- `personal_data_class`
- `validation`
- `import_aliases`

### Data Query

- `search`
- `filters`: field/operator/value list
- `sort_field`, `sort_direction`
- `page`, `per_page`
- `include_test`

### Mutation Preview

- `operation_id`, `source_key`, `kind`
- `record_ids`, `proposed_changes`
- `matched_count`, `accessible_count`, `blocked_count`
- `warnings`, `personal_data_classes`
- `target_hash`, `expires_at`

### Import Run

- `id`, `source_key`, `actor_id`
- `state`: uploaded, mapping, validating, valid, invalid, committing, completed, partial, failed, cancelled
- `file_name`, `file_hash`, `format`, `encoding`
- `mapping_json`, `unknown_column_policy`
- `total_rows`, `valid_rows`, `rejected_rows`, `committed_rows`
- `personal_data_classes`
- `report_artifact`
- `job_id`, `created_at`, `finished_at`

**Transitions**: uploaded → mapping → validating → valid | invalid; valid → committing → completed | partial | failed. Only `valid` may commit and the file/mapping checksum must match the dry run.

### Export Run

- `id`, `source_key`, `actor_id`
- `scope`, `query_json`, `selected_ids`, `columns`, `format`
- `include_test`, `personal_data_classes`
- `state`: queued, running, completed, failed, expired
- `row_count`, `artifact`, `job_id`
- `created_at`, `completed_at`, `expires_at`

### Migration Definition and Run

- Definition: `key`, `version`, `description`, `plan`, `transactional`, `rollback_supported`
- Run: `id`, `key`, `version`, `actor_id`, `state`, `snapshot_id`, `started_at`, `finished_at`, `error_code`

**Transitions**: pending → previewed → snapshotting → running → applied | failed; applied → rolling_back → rolled_back | rollback_failed.

## Operations and Security

### Operations Mode State

- `mode`: development, staging, production, maintenance
- `source`: explicit, environment, config
- `changed_by`, `changed_at`
- `override_reason`, `readiness_snapshot_id`

### Readiness Check Result

- `key`, `label`, `area`
- `state`: pass, warning, blocking, unavailable
- `summary`, `resolution_url`
- `checked_at`, `evidence_hash`

### Login Security Policy

- `enabled`
- `custom_slug`
- `block_default_endpoints`
- `threshold`, `window_seconds`, `lockout_seconds`
- `captcha_driver`
- `trusted_proxy_mode`, `trusted_proxy_ranges`
- `retain_days`
- `successful_login_logging`
- `updated_by`, `updated_at`

### Login Attempt

- `id`, `occurred_at`
- `identity_hash`, `network_hash`
- `outcome`: failed, locked, success
- `reason_code`, `user_id`
- `retention_until`

Raw passwords, raw submitted credentials, and analytics-grade raw IP storage are forbidden.

## Email Studio

### Email Template

- `id`, `uuid`, `slug`, `name`, `status`: draft, active, inactive
- `draft_version`, `active_version`
- `updated_by`, `updated_at`

### Email Template Version

- `id`, `template_id`, `version_number`
- `subject`, `from_name`, `from_address`
- `html_body`, `plain_text`, `plain_text_mode`: auto or manual
- `layout_id`, `layout_version`
- `variable_keys`
- `created_by`, `created_at`, `checksum`

### Email Layout

- `id`, `slug`, `name`, `status`
- `header_json`, `accent_json`, `body_json`, `button_json`, `footer_json`
- `dependency`: nullable external dependency
- `version`, `updated_at`

### Email Partial

- `id`, `slug`, `name`, `kind`: header, footer, unsubscribe, preferences, privacy, custom
- `html_body`, `plain_text`, `status`, `version`

### Email Route

- `id`, `trigger_key`, `flow_id`
- `template_id`, `template_version_policy`
- `recipient_rules`, `reply_to_rule`
- `enabled`, `priority`

### Email Attempt

- `id`, `uuid`, `parent_attempt_id`
- `template_id`, `template_version`, `route_id`
- `recipient_redacted`, `recipient_hash`
- `subject_snapshot`
- `environment`, `provider_key`
- `state`: captured, queued, sending, sent, failed, bounced, opened
- `provider_message_id`, `error_code`
- `created_at`, `updated_at`

### Captured Email

- `attempt_id`
- `to`, `subject`, `html_body`, `plain_text`, `headers`
- `captured_at`, `retention_until`

Capture is restricted personal data and follows email-log retention.

## Blog Pro

### Editorial Metadata

- `post_id`
- `review_state`: draft, ready_review, needs_changes, approved, scheduled, published
- `assignee_id`, `due_at`
- `updated_by`, `updated_at`

### Editorial Note

- `id`, `post_id`, `author_id`, `body`, `created_at`

### Reading Event

- `id`, `post_id`, `occurred_at`
- `event`: view, engaged_read, share_click
- `visitor_day_hash`: nullable consent-aware short-lived uniqueness token
- `duration_bucket`: nullable bounded read-time bucket
- `referrer_class`, `platform_key`
- `retention_until`

### Blog Aggregate

- `post_id`, `period_start`, `period_end`
- `views`, `reads`, `unique_readers`, `read_time_seconds`, `share_clicks`, `comments`
- `computed_at`

## Insights

### Insight Provider State

- `key`, `label`
- `connection_state`: connected, disconnected, setup_required, error
- `configured_at`, `last_error_code`, `setup_url`

### Insight Run

- `id`, `provider_key`, `actor_id`
- `state`: queued, running, completed, error
- `started_at`, `finished_at`
- `result_json`, `recommendations_json`
- `environment`, `target_url`

## Setup and Settings

### Setup Progress

- `id` or site singleton
- `current_step`
- `step_states`: not_started, in_progress, complete, skipped, blocked
- `percent`
- `brand_config`, `kit_key`, `demo_level`
- `updated_by`, `updated_at`

### Setup Plan

- `id`, `actor_id`
- `brand_changes`, `kit_changes`, `content_items`, `config_changes`
- `conflicts`: target, current snapshot, proposed snapshot, choice
- `backup_id`, `target_hash`
- `state`: previewed, backed_up, applying, applied, partial, failed, rolling_back, rolled_back
- `job_id`, `created_at`, `finished_at`

### Backup Snapshot

- `id`, `kind`, `created_by`, `created_at`
- `manifest`, `artifact`, `checksum`
- `restorable_until`, `restored_at`

### Settings Revision

- `id`, `section`, `actor_id`
- `old_values_redacted`, `new_values_redacted`
- `created_at`, `activity_event_id`

Secrets are stored through the existing write-only configuration boundary and never copied into revision or activity payloads.

## Bounded Job

- `id`, `kind`, `actor_id`
- `state`: queued, running, paused, completed, partial, failed, cancelled
- `cursor`, `total`, `processed`, `succeeded`, `failed`
- `input_hash`, `result_artifact`, `error_summary`
- `attempts`, `next_run_at`
- `created_at`, `updated_at`, `finished_at`

**Constraints**: one active idempotency key per operation; each step processes a configured maximum; cancellation stops future steps but does not undo committed work unless the domain provides rollback.
