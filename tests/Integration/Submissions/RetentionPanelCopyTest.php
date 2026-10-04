<?php

/**
 * What the retention panel says it will do, and what it says it did (DECISIONS #233).
 *
 * The form offers Archive, Move to trash and Anonymize personal data. Its confirmation box and its
 * result notice both said "trash" for all three, so an operator who anonymized 40 submissions — which
 * cannot be undone — was asked to confirm a move to "the recoverable trash" and then told 40
 * submissions had been moved there.
 *
 * Nothing here reads or writes a submission: both methods under test only turn the request into
 * markup, so the suite's real install is not touched.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Submissions\SubmissionsInboxScreen;

/**
 * `pruneForm()` and `retentionNotice()` are private, which is correct — they are parts of
 * `render()`, and `render()` only prints the prune form when the install happens to hold due
 * submissions. Reflection reaches them without widening the production API to suit a test.
 */
function retentionPanelPart(string $method): string
{
    $screen = Boot::app()->container()->make(SubmissionsInboxScreen::class);

    return (string) (new ReflectionMethod($screen, $method))->invoke($screen);
}

/**
 * The notice the screen renders after a retention handler redirects back to it.
 *
 * @param array<string,string> $query What the redirect carried. The status is a completed run
 *                                    unless the query names another.
 */
function retentionResultNotice(array $query): string
{
    $saved = $_GET;
    $_GET  = $query + ['corex_status' => 'retention-pruned'];

    try {
        return retentionPanelPart('retentionNotice');
    } finally {
        $_GET = $saved;
    }
}

it('says which action ran, in the singular and the plural', function (string $action, string $count, string $sentence) {
    $notice = retentionResultNotice(['corex_action' => $action, 'corex_count' => $count]);

    expect($notice)->toContain($sentence);
})->with([
    'one archived'    => ['archive', '1', '1 submission archived.'],
    'many archived'   => ['archive', '3', '3 submissions archived.'],
    'one trashed'     => ['trash', '1', '1 submission moved to trash.'],
    'many trashed'    => ['trash', '3', '3 submissions moved to trash.'],
    'one anonymized'  => ['anonymize', '1', '1 submission anonymized.'],
    'many anonymized' => ['anonymize', '3', '3 submissions anonymized.'],
]);

/**
 * The regression itself: these two runs leave every record out of the trash, and the notice said
 * otherwise.
 */
it('does not report a move to trash for a run that trashed nothing', function (string $action) {
    $notice = retentionResultNotice(['corex_action' => $action, 'corex_count' => '3']);

    expect($notice)->not->toContain('trash');
})->with(['archive', 'anonymize']);

/**
 * `corex_action` arrives in the address bar, so it is whatever somebody typed — and a link saved
 * before this change carries none. Neither may be answered with a guess at what ran.
 */
it('names no action when the redirect carries none it knows', function (array $query) {
    $notice = retentionResultNotice($query + ['corex_count' => '3']);

    expect($notice)->toContain('Retention applied to 3 submissions.')
        ->and($notice)->not->toContain('trash');
})->with([
    'no action'         => [[]],
    'an unknown action' => [['corex_action' => 'delete']],
]);

/**
 * Applying retention without ticking the confirmation box runs nothing. The notice that said so
 * was drawn as a success, tick and all, so a refused run looked like one that worked.
 */
it('does not draw a run that was refused as a success', function () {
    $notice = retentionResultNotice(['corex_status' => 'retention-confirm']);

    expect($notice)->toContain('Confirm the retention action before applying it.')
        ->and($notice)->toContain('corex-state--warning')
        ->and($notice)->not->toContain('corex-state--success');
});

it('still draws a saved policy and a completed run as a success', function (array $query) {
    expect(retentionResultNotice($query))->toContain('corex-state--success');
})->with([
    'a saved policy'  => [['corex_status' => 'retention-saved']],
    'a completed run' => [['corex_status' => 'retention-pruned', 'corex_action' => 'archive', 'corex_count' => '3']],
]);

it('asks the operator to confirm the action they selected, not a move to trash', function () {
    $form = retentionPanelPart('pruneForm');

    expect($form)->toContain('Confirm applying the selected action to the due submissions.')
        ->and($form)->not->toContain('recoverable');
});

it('warns that anonymizing cannot be undone, beside the confirmation', function () {
    expect(retentionPanelPart('pruneForm'))->toContain('Anonymizing cannot be undone.');
});
