<?php

/**
 * Teams a submission can be routed to (spec 122, FR-012), and the hooks that register flow
 * extensions (FR-021).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Flow\EmailVariableRegistry;
use Corex\Forms\Flow\FlowActionRegistry;
use Corex\Forms\Flow\FlowExtensionHooks;
use Corex\Forms\Routing\RoutingTargetRegistry;
use Corex\Forms\Routing\Teams\Team;
use Corex\Forms\Routing\Teams\TeamService;
use Corex\Forms\Routing\Teams\TeamStore;
use Corex\Support\BootLogger;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('sanitize_key')->alias(static fn (string $value): string => preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?? '');
    Functions\when('absint')->alias(static fn (mixed $value): int => abs((int) $value));
    Functions\when('remove_accents')->returnArg();
});

function memoryTeams(): TeamService
{
    return new TeamService(new class implements TeamStore {
        /** @var list<Team> */
        private array $teams = [];

        public function all(): array
        {
            return $this->teams;
        }

        public function replace(array $teams): void
        {
            $this->teams = $teams;
        }
    });
}

it('creates a team with a key made from its name, and a second of the same name gets its own key', function () {
    $teams = memoryTeams();

    $first = $teams->create('Sales Team', [3, '4', 4, 0]);
    $second = $teams->create('Sales Team', []);

    expect($first->toArray())->toBe(['key' => 'sales-team', 'name' => 'Sales Team', 'members' => [3, 4]])
        ->and($second->key)->toBe('sales-team-2');
});

it('renames a team and changes its members, keeping its key', function () {
    $teams = memoryTeams();
    $team = $teams->create('Sales', [3]);

    $changed = $teams->update($team->key, 'Sales and partners', [3, 5]);

    expect($changed->toArray())->toBe(['key' => 'sales', 'name' => 'Sales and partners', 'members' => [3, 5]])
        ->and($teams->teamsOf(5))->toBe(['sales'])
        ->and($teams->teamsOf(9))->toBe([]);
});

it('refuses a team with no name and a change to a team that is gone', function () {
    $teams = memoryTeams();

    expect(fn () => $teams->create('  ', []))->toThrow(DomainException::class, 'A team needs a name.')
        ->and(fn () => $teams->update('nobody', 'Name', []))->toThrow(DomainException::class, 'The team was not found.')
        ->and(fn () => $teams->delete('nobody'))->toThrow(DomainException::class, 'The team was not found.');
});

it('removes a team', function () {
    $teams = memoryTeams();
    $team = $teams->create('Support', [2]);

    $teams->delete($team->key);

    expect($teams->all())->toBe([]);
});

it('registers the actions, email variables and routing targets the documented hooks return', function () {
    $actions = new FlowActionRegistry();
    $variables = new EmailVariableRegistry();
    $targets = new RoutingTargetRegistry();
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => match ($hook) {
        'corex_flow_actions' => ['crm_push' => ['label' => 'Send to the CRM', 'callback' => static fn (): bool => true]],
        'corex_flow_email_variables' => [
            'crm.id' => ['label' => 'CRM record', 'callback' => static fn (): string => 'CRM-1'],
            'broken' => ['label' => 'No callback'],
        ],
        'corex_flow_routing_targets' => ['by_region' => ['label' => 'By region', 'callback' => static fn (): int => 3]],
        default => $value,
    });

    (new FlowExtensionHooks($actions, $variables, $targets, new BootLogger(debug: false)))->register();

    expect($actions->definitions())->toBe([['key' => 'crm_push', 'label' => 'Send to the CRM']])
        ->and($variables->definitions())->toBe([['key' => 'crm.id', 'label' => 'CRM record']])
        ->and($targets->definitions())->toBe([['key' => 'by_region', 'label' => 'By region']]);
});
