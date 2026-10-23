<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Forms\Routing\RoutingCondition;
use Corex\Forms\Routing\RoutingTarget;
use Corex\Forms\Schema\FieldTypeRegistry;
use Corex\Forms\Submission\FlowEmailVariables;
use Corex\Forms\Success\SuccessStateRegistry;
use Corex\Mail\MailTemplateCatalog;
use Corex\Forms\Validation\RuleRegistry;
use DomainException;
use InvalidArgumentException;

/**
 * Fail-closed publication validation for one immutable flow configuration.
 */
final readonly class FlowConfigurationValidator
{
    public function __construct(
        private FieldTypeRegistry $fieldTypes,
        private RuleRegistry $rules,
        private SuccessStateRegistry $successStates,
        /** What each Email Studio template uses, where CoreX Mail is active (spec 120). */
        private ?MailTemplateCatalog $templates = null,
    ) {
    }

    public function validate(FlowConfiguration $configuration): void
    {
        try {
            $fieldKeys = $this->validateSchema($configuration);
            $this->validateRules($configuration, $fieldKeys);
            $this->validateRouting($configuration, $fieldKeys);
            $this->validateEmails($configuration);
            $this->validateSuccess($configuration);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException($exception->getMessage(), 0, $exception);
        }
    }

    /** @return list<string> */
    private function validateSchema(FlowConfiguration $configuration): array
    {
        if ($configuration->schema === []) {
            throw new DomainException(__('A published flow requires at least one field.', 'corex'));
        }
        $keys = [];
        foreach ($configuration->schema as $field) {
            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? '');
            if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1 || in_array($key, $keys, true)) {
                throw new DomainException(__('Flow field keys must be unique and canonical.', 'corex'));
            }
            if (! $this->fieldTypes->has($type)) {
                throw new DomainException(__('A flow field uses an unavailable type.', 'corex'));
            }
            $keys[] = $key;
        }

        return $keys;
    }

    /** @param list<string> $fieldKeys */
    private function validateRules(FlowConfiguration $configuration, array $fieldKeys): void
    {
        foreach ($configuration->validation as $fieldKey => $specs) {
            if (! in_array((string) $fieldKey, $fieldKeys, true)) {
                throw new DomainException(__('Validation references an unavailable flow field.', 'corex'));
            }
            foreach ((array) $specs as $spec) {
                $rule = $this->rules->parse((string) $spec);
                if (! $this->rules->has($rule['name'])) {
                    throw new DomainException(__('A flow field uses an unavailable validation rule.', 'corex'));
                }
            }
        }
    }

    /** @param list<string> $fieldKeys */
    private function validateRouting(FlowConfiguration $configuration, array $fieldKeys): void
    {
        $fallback = $configuration->routing['fallback'] ?? null;
        if (! is_array($fallback)) {
            throw new DomainException(__('A published flow requires a routing fallback.', 'corex'));
        }
        $this->validateTarget($fallback, $fieldKeys);
        foreach ((array) ($configuration->routing['rules'] ?? []) as $rule) {
            if (! is_array($rule) || ! ($rule['enabled'] ?? true)) {
                continue;
            }
            $condition = (array) ($rule['condition'] ?? []);
            $field = (string) ($condition['field'] ?? '');
            if (! in_array($field, $fieldKeys, true)) {
                throw new DomainException(__('A routing rule references an unavailable flow field.', 'corex'));
            }
            new RoutingCondition($field, (string) ($condition['operator'] ?? ''), $condition['value'] ?? null);
            $this->validateTarget((array) ($rule['target'] ?? []), $fieldKeys);
        }
    }

    /**
     * @param array<string,mixed> $definition
     * @param list<string>        $fieldKeys
     */
    private function validateTarget(array $definition, array $fieldKeys): void
    {
        $target = new RoutingTarget((string) ($definition['type'] ?? ''), (array) ($definition['config'] ?? []));
        $value = $target->config['value'] ?? null;
        $valid = match ($target->type) {
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'user' => (int) $value > 0,
            'role', 'team', 'department', 'extension' => is_string($value) && trim($value) !== '',
            'field_value' => is_string($value) && in_array($value, $fieldKeys, true),
            default => true,
        };
        if (! $valid) {
            throw new DomainException(__('A routing target is incomplete or invalid.', 'corex'));
        }
    }

    private function validateEmails(FlowConfiguration $configuration): void
    {
        $events = ['submitter_confirmation', 'team_notification', 'admin_failure'];
        foreach ($configuration->emailRoutes as $binding) {
            if (! is_array($binding) || ! ($binding['enabled'] ?? false)) {
                continue;
            }
            if (! in_array((string) ($binding['event'] ?? ''), $events, true)
                || (int) ($binding['template_id'] ?? 0) < 1
                || trim((string) ($binding['recipient'] ?? '')) === ''
            ) {
                throw new DomainException(__('An enabled flow email binding is incomplete.', 'corex'));
            }
            $this->refuseUnsuppliedVariables($binding, $configuration);
        }
    }

    /**
     * A template bound to a flow's email may use only what that email supplies, unless whoever
     * bound it confirmed sending it without the rest (spec 120, story 5).
     *
     * @param array<string,mixed> $binding
     */
    private function refuseUnsuppliedVariables(array $binding, FlowConfiguration $configuration): void
    {
        if ($this->templates === null || ($binding['accept_missing'] ?? false) === true) {
            return;
        }
        foreach ($this->templates->templates() as $template) {
            if ((int) ($template['id'] ?? 0) !== (int) $binding['template_id']) {
                continue;
            }
            $missing = FlowEmailVariables::missing(
                array_values(array_filter((array) ($template['variables'] ?? []), 'is_string')),
                $configuration,
                (string) $binding['event'],
            );
            if ($missing !== []) {
                throw new DomainException(sprintf(
                    /* translators: 1: an Email Studio template's name. 2: variable keys, comma separated. */
                    __('The template %1$s uses %2$s, which this form does not supply. Remove it from the template, or confirm sending the email without it in the Emails tab.', 'corex'),
                    (string) ($template['name'] ?? ''),
                    implode(', ', $missing),
                ));
            }
        }
    }

    private function validateSuccess(FlowConfiguration $configuration): void
    {
        $type = (string) ($configuration->success['type'] ?? '');
        if (! $this->successStates->has($type)) {
            throw new DomainException(__('A published flow requires a supported success state.', 'corex'));
        }
        $success = $this->successStates->normalize($type, $configuration->success);
        $valid = match ($type) {
            'inline' => trim((string) ($success['message'] ?? '')) !== '',
            'page' => (int) ($success['page_id'] ?? 0) > 0,
            'url' => filter_var($success['url'] ?? '', FILTER_VALIDATE_URL) !== false,
            default => true,
        };
        if (! $valid) {
            throw new DomainException(__('The configured success behavior is incomplete.', 'corex'));
        }
    }
}
