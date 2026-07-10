<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Forms\Routing\RoutingTarget;
use Corex\Forms\Schema\FieldTypeRegistry;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Mail\MailTemplateCatalog;

/**
 * Read-only projection of extensions available to the flow editor.
 */
final readonly class FlowExtensionCatalog
{
    public function __construct(
        private FieldTypeRegistry $fieldTypes,
        private RuleRegistry $rules,
        private FlowBehaviorRegistries $behaviors,
        private ?MailTemplateCatalog $templates = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        $fields = [];
        foreach ($this->fieldTypes->keys() as $key) {
            $fields[] = $this->fieldTypes->get($key);
        }

        return [
            'field_types' => $fields,
            'validation_rules' => $this->rules->keys(),
            'flow_actions' => $this->behaviors->actions->definitions(),
            'routing_targets' => RoutingTarget::types(),
            'email_variables' => $this->behaviors->emailVariables->definitions(),
            'email_templates' => $this->templates?->templates() ?? [],
            'success_states' => $this->behaviors->successStates->keys(),
        ];
    }
}
