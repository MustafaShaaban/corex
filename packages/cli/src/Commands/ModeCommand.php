<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Config\Operations\ModeChangeRequest;
use Corex\Config\Operations\ModeChangeResult;
use Corex\Config\Operations\ModeChangeService;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use DateTimeImmutable;

/**
 * `wp corex mode get|set` (spec 101, FR-017): read the operations mode, and change it, from the
 * command line — for a deploy script that puts a fresh install into Coming soon before anybody
 * visits it, and for an operator who cannot reach the screen.
 *
 * It holds no rule about changing mode. {@see ModeChangeService} does, and it is the same service
 * the Operations screen calls, so the command cannot be a way round a confirmation: `--acknowledge`
 * is the ticked box and `--phrase` is the typed phrase, and without the one its mode needs nothing
 * changes and the command fails. What this class owns is reading its arguments and saying what
 * happened in words and an exit code.
 */
final class ModeCommand
{
    public function __construct(
        private readonly ModeChangeService $changes,
        private readonly OperationsModeStore $store,
        private readonly OperationsMode $modes,
    ) {
    }

    /**
     * The command without the printing.
     *
     * @param list<string>             $args  Positional arguments after the action.
     * @param array<string,mixed>      $assoc Flags.
     * @param int                      $actorId The user the change is recorded against; 0 when
     *                                          WP-CLI was not given `--user`.
     */
    public function execute(string $action, array $args, array $assoc, int $actorId, DateTimeImmutable $now): ModeCommandResult
    {
        return match ($action) {
            'get'   => $this->get(),
            'set'   => $this->set($args, $assoc, $actorId, $now),
            default => $this->failure(__('Usage: wp corex mode get, or wp corex mode set <mode>.', 'corex')),
        };
    }

    /**
     * @param list<string>        $args
     * @param array<string,mixed> $assoc
     */
    public function run(string $action, array $args, array $assoc): void
    {
        $result = $this->execute($action, $args, $assoc, get_current_user_id(), new DateTimeImmutable('now'));

        // `get --porcelain` prints the mode and nothing else, for a script to read.
        if ($action === 'get' && ! empty($assoc['porcelain'])) {
            \WP_CLI::line($result->mode);

            return;
        }

        if ($result->shouldExitNonZero()) {
            \WP_CLI::error($result->message);
        }

        $action === 'get' ? \WP_CLI::log($result->message) : \WP_CLI::success($result->message);
    }

    private function get(): ModeCommandResult
    {
        $mode = $this->store->current();

        return new ModeCommandResult(
            true,
            $mode,
            $this->store->isDeclared()
                /* translators: %s: operations mode. */
                ? sprintf(__('Operations mode: %s (declared).', 'corex'), $mode)
                /* translators: %s: operations mode. */
                : sprintf(__('Operations mode: %s (inherited from the WordPress environment type; no mode has been declared).', 'corex'), $mode),
        );
    }

    /**
     * @param list<string>        $args
     * @param array<string,mixed> $assoc
     */
    private function set(array $args, array $assoc, int $actorId, DateTimeImmutable $now): ModeCommandResult
    {
        $mode = isset($args[0]) ? strtolower(trim((string) $args[0])) : '';
        if ($mode === '') {
            return $this->failure(__('Name the mode: wp corex mode set <mode>.', 'corex'));
        }

        // A launch is recorded against a person, and the launch service refuses to record one
        // against nobody. WP-CLI is nobody unless told otherwise, so say what to pass.
        if ($mode === OperationsMode::PRODUCTION && $actorId < 1) {
            return $this->failure(__('Going live is recorded against a user. Re-run with --user=<login>.', 'corex'));
        }

        $result = $this->changes->apply(new ModeChangeRequest(
            mode: $mode,
            actorId: $actorId,
            now: $now,
            acknowledged: ! empty($assoc['acknowledge']),
            phrase: isset($assoc['phrase']) ? (string) $assoc['phrase'] : '',
        ));

        return match ($result->status) {
            ModeChangeResult::SAVED => new ModeCommandResult(
                true,
                $result->applied,
                /* translators: %s: operations mode. */
                sprintf(__('Operations mode is now %s.', 'corex'), $result->applied),
            ),
            // The state asked for is the state found. That is a success: a deploy script that
            // sets the mode on every run must not fail on its second.
            ModeChangeResult::UNCHANGED => new ModeCommandResult(
                true,
                $result->applied,
                /* translators: %s: operations mode. */
                sprintf(__('No change: the site was already in %s.', 'corex'), $result->applied),
            ),
            ModeChangeResult::NEEDS_ACKNOWLEDGEMENT => $this->failure(sprintf(
                /* translators: %s: operations mode. */
                __('%s changes what visitors get. Nothing was changed. Re-run with --acknowledge if that is what you intend.', 'corex'),
                $mode,
            )),
            ModeChangeResult::NEEDS_PHRASE => $this->failure(
                __('Going live needs the typed confirmation. Nothing was changed. Re-run with --phrase=PRODUCTION.', 'corex'),
            ),
            ModeChangeResult::BLOCKED => $this->failure(
                __('The production launch was blocked by readiness checks. Nothing was changed.', 'corex'),
            ),
            default => $this->failure(sprintf(
                /* translators: 1: the value given, 2: the list of valid modes. */
                __('"%1$s" is not an operations mode. Available: %2$s.', 'corex'),
                $mode,
                implode(', ', $this->modes->all()),
            )),
        };
    }

    private function failure(string $message): ModeCommandResult
    {
        return new ModeCommandResult(false, $this->store->current(), $message);
    }
}
