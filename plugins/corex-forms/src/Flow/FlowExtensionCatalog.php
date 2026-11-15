<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Forms\Routing\RoutingDirectory;
use Corex\Forms\Routing\RoutingPerson;
use Corex\Forms\Routing\RoutingTargetRegistry;
use Corex\Forms\Routing\Teams\Team;
use Corex\Forms\Routing\Teams\TeamService;
use Corex\Forms\Schema\FieldTypeRegistry;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Mail\MailTemplateCatalog;

/**
 * Read-only projection of what the flow editor can offer.
 *
 * Routing targets are the six spec 122 keeps (FLW-D5) plus every registered routing extension,
 * each with its label; the people, teams and roles a target can name come with them, so the
 * editor picks a person by name and never types an ID.
 */
final readonly class FlowExtensionCatalog
{
    /** How many people the editor's pickers list. A site with more picks a team or a role. */
    private const PEOPLE_LISTED = 200;

    public function __construct(
        private FieldTypeRegistry $fieldTypes,
        private RuleRegistry $rules,
        private FlowBehaviorRegistries $behaviors,
        private RoutingTargetRegistry $routingTargets,
        private RoutingDirectory $directory,
        private TeamService $teams,
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
            'routing_targets' => $this->routingTargets(),
            'email_variables' => $this->behaviors->emailVariables->definitions(),
            'email_templates' => $this->templates?->templates() ?? [],
            'success_states' => $this->behaviors->successStates->keys(),
            'people' => array_map(
                static fn (RoutingPerson $person): array => ['id' => $person->id, 'name' => $person->name],
                $this->directory->people(self::PEOPLE_LISTED),
            ),
            'teams' => array_map(static fn (Team $team): array => $team->toArray(), $this->teams->all()),
            'roles' => $this->roles(),
        ];
    }

    /** @return list<array{key:string,name:string}> */
    private function roles(): array
    {
        $roles = [];
        foreach ($this->directory->roles() as $key => $name) {
            $roles[] = ['key' => (string) $key, 'name' => $name];
        }

        return $roles;
    }

    /** @return list<array{key:string,label:string,extension?:string}> */
    private function routingTargets(): array
    {
        $targets = [
            ['key' => 'user', 'label' => __('User', 'corex')],
            ['key' => 'team', 'label' => __('Team', 'corex')],
            ['key' => 'role', 'label' => __('Role', 'corex')],
            ['key' => 'flow_owner', 'label' => __('Flow owner', 'corex')],
            ['key' => 'page_owner', 'label' => __('Page owner', 'corex')],
            ['key' => 'field_value', 'label' => __('Field value', 'corex')],
        ];
        foreach ($this->routingTargets->definitions() as $definition) {
            $targets[] = ['key' => 'extension', 'label' => $definition['label'], 'extension' => $definition['key']];
        }

        return $targets;
    }
}
