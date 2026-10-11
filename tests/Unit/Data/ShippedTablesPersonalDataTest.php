<?php

/**
 * Every managed table CoreX and its add-ons ship says what each of its columns holds
 * (spec 109, FR-016).
 *
 * A column a table says nothing about is "undeclared", and an undeclared column is treated as
 * personal data. That is the safe reading of a table nobody has looked at. It is the wrong one
 * for a table somebody has: the jobs log would ask for the right to export personal data. So the
 * tables shipped here are held to having answered for every column.
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Careers\Application\ApplicationTable;
use Corex\Config\Access\AccessTables;
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Blog\ReadingEventTable;
use Corex\Config\Jobs\JobTable;
use Corex\Config\Notifications\NotificationTable;
use Corex\Config\Notifications\NotificationUserStateTable;
use Corex\Config\Security\LoginProtection\LoginAttemptTable;
use Corex\Data\DataField;
use Corex\Database\Schema\ManagedTable;
use Corex\Newsletter\Subscriber\SubscriberTable;

/** @return array<string,ManagedTable> Every shipped managed table, by its name. */
function shippedManagedTables(): array
{
    $tables = [
        ...(new AccessTables())->managed(),
        (new ActivityTable())->managed(),
        (new ReadingEventTable())->managed(),
        (new JobTable())->managed(),
        (new NotificationTable())->managed(),
        (new NotificationUserStateTable())->managed(),
        (new LoginAttemptTable())->managed(),
        (new SubscriberTable())->managed(),
        (new ApplicationTable())->managed(),
    ];

    $byName = [];
    foreach ($tables as $table) {
        $byName[$table->name] = $table;
    }

    return $byName;
}

/** @return array<string,string> A table's columns, each with the class it declared. */
function declaredClasses(ManagedTable $table): array
{
    $classes = [];
    foreach ($table->columnIds() as $column) {
        $classes[$column] = $table->personalDataClass($column);
    }

    return $classes;
}

it('leaves no column of a shipped table undeclared', function () {
    $undeclared = [];
    foreach (shippedManagedTables() as $name => $table) {
        foreach (declaredClasses($table) as $column => $class) {
            if ($class === DataField::PERSONAL_UNDECLARED) {
                $undeclared[] = $name . '.' . $column;
            }
        }
    }

    expect($undeclared)->toBe([]);
});

it('marks the columns that hold something about a person', function (string $table, array $personal) {
    $classes = array_filter(
        declaredClasses(shippedManagedTables()[$table]),
        static fn (string $class): bool => $class !== DataField::PERSONAL_NONE,
    );

    expect($classes)->toBe($personal);
})->with([
    'a subscriber' => [SubscriberTable::NAME, [
        'email' => DataField::PERSONAL_CONTACT,
        'topics' => DataField::PERSONAL_BEHAVIORAL,
    ]],
    'a job application' => [ApplicationTable::NAME, [
        'name' => DataField::PERSONAL_IDENTITY,
        'email' => DataField::PERSONAL_CONTACT,
        'cv_attachment' => DataField::PERSONAL_SENSITIVE,
    ]],
    'a login attempt' => [LoginAttemptTable::NAME, [
        'user_id' => DataField::PERSONAL_SECURITY,
    ]],
    'an activity event' => [ActivityTable::NAME, [
        'actor_label' => DataField::PERSONAL_IDENTITY,
        'target_label' => DataField::PERSONAL_IDENTITY,
    ]],
    'an access request' => [AccessTables::REQUESTS, [
        'requester_id' => DataField::PERSONAL_IDENTITY,
    ]],
    'a notification read state' => [NotificationUserStateTable::NAME, [
        'user_id' => DataField::PERSONAL_IDENTITY,
    ]],
]);

it('marks nothing as personal in a table that holds nothing about a person', function (string $table) {
    $classes = array_unique(array_values(declaredClasses(shippedManagedTables()[$table])));

    expect($classes)->toBe([DataField::PERSONAL_NONE]);
})->with([
    'role grants' => [AccessTables::ROLE_GRANTS],
    'jobs' => [JobTable::NAME],
    'notifications' => [NotificationTable::NAME],
    'reading events' => [ReadingEventTable::NAME],
]);

it('lists what a site keeps for its work as records, and its own history as logs', function () {
    $categories = array_map(static fn (ManagedTable $table): string => $table->category(), shippedManagedTables());
    $records = array_keys($categories, ManagedTable::CATEGORY_RECORDS, true);
    sort($records);

    // Spec 109, FR-003: the Export tab's two groups.
    expect($records)->toBe([ApplicationTable::NAME, SubscriberTable::NAME])
        ->and(array_keys($categories, ManagedTable::CATEGORY_LOG, true))->toHaveCount(8);
});
