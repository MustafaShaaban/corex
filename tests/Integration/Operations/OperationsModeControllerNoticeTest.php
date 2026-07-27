<?php

/**
 * The mode-change notice tells the truth about what happened (spec 077, T004 / FR-009).
 *
 * A "saved" notice over a change that did not happen is a small lie with a real cost: it teaches
 * the operator that this notice means nothing, on the one screen where a notice has to mean
 * something. The store already refuses to log a non-change (see OperationsModeNoOpTest); this is
 * the half the operator actually reads.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

beforeEach(function () {
    delete_option('corex_operations_mode');
    delete_option('corex_operations_mode_log');
    $this->store = new OperationsModeStore(new OperationsMode());
});

afterEach(function () {
    delete_option('corex_operations_mode');
    delete_option('corex_operations_mode_log');
});

it('renders a distinct notice for a change that did not happen', function () {
    $screen = corexOperationsScreen();

    $_GET['corex_status'] = 'unchanged';
    $unchanged = renderNotice($screen);

    $_GET['corex_status'] = 'saved';
    $saved = renderNotice($screen);

    unset($_GET['corex_status']);

    expect($unchanged)->not->toBe('')
        ->and($unchanged)->not->toBe($saved)
        // It must not claim an update, and it must not read as an error either — nothing went
        // wrong, nothing happened.
        ->and($unchanged)->not->toContain('updated')
        ->and($unchanged)->toContain('already in that mode');
});

it('still reports a real change as saved', function () {
    $screen = corexOperationsScreen();

    $_GET['corex_status'] = 'saved';
    $saved = renderNotice($screen);
    unset($_GET['corex_status']);

    expect($saved)->toContain('updated');
});

/**
 * The screen, resolved from the container so its real collaborators are used.
 */
function corexOperationsScreen(): object
{
    return \Corex\Boot::app()->container()->make(
        \Corex\Config\Security\OperationsSecurityScreen::class
    );
}

/**
 * `statusNotice()` is private, which is correct — it is an implementation detail of `render()`.
 * Reflection is used rather than making it public, because widening a method's visibility so a test
 * can reach it changes the production API to suit the test.
 */
function renderNotice(object $screen): string
{
    $method = new ReflectionMethod($screen, 'statusNotice');
    $method->setAccessible(true);

    return (string) $method->invoke($screen);
}
