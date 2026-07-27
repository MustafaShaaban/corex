<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * What a proposed operations mode actually asks of the operator (spec 077, FR-006/FR-007).
 *
 * One answer, three consumers: the server renders the block this describes, the client swaps
 * between blocks using the same description, and the controller validates against it. Before this,
 * the form rendered *every* confirmation for *every* mode — a site in Development still showed the
 * "Type PRODUCTION" field and the "I understand maintenance affects real visitors" checkbox. Being
 * asked to acknowledge consequences that cannot occur teaches an operator that confirmations are
 * noise, which is the lesson that makes them dangerous when they are real.
 *
 * Deliberately a pure description. It evaluates no readiness, checks no capability and reads no
 * option, so the same object answers identically on the server, in a unit test with no WordPress
 * booted, and for a mode the site is merely *considering*. Readiness and capability are decided by
 * the services that own them; this only says which questions belong to which mode.
 *
 * `OperationsMode::requiresConfirmation()` already knew that production and maintenance need a
 * confirmation. What it could not say is **which** confirmation — and that distinction is the whole
 * defect: two different questions had collapsed into one boolean, so both fields were rendered
 * whenever either applied.
 */
final class ModeDisclosure
{
    /** A typed phrase, because going live is not something to agree to by reflex. */
    public const CONFIRM_PHRASE = 'phrase';

    /** A ticked acknowledgement, because the consequence is immediate and visible to strangers. */
    public const CONFIRM_ACKNOWLEDGEMENT = 'acknowledgement';

    /** Nothing to confirm. Changing to this mode harms nobody. */
    public const CONFIRM_NONE = 'none';

    public function __construct(private readonly OperationsMode $modes)
    {
    }

    /**
     * Which confirmation this mode requires — the question, not merely whether there is one.
     */
    public function confirmationFor(string $mode): string
    {
        return match ($this->modes->normalize($mode)) {
            OperationsMode::PRODUCTION  => self::CONFIRM_PHRASE,
            OperationsMode::MAINTENANCE => self::CONFIRM_ACKNOWLEDGEMENT,
            default                     => self::CONFIRM_NONE,
        };
    }

    public function requiresPhrase(string $mode): bool
    {
        return $this->confirmationFor($mode) === self::CONFIRM_PHRASE;
    }

    public function requiresAcknowledgement(string $mode): bool
    {
        return $this->confirmationFor($mode) === self::CONFIRM_ACKNOWLEDGEMENT;
    }

    /**
     * What this mode means, in the operator's terms, and what it is fair to warn them about.
     *
     * Every line is something the code actually does. The maintenance lines in particular are
     * verifiable against {@see MaintenanceGuard}: anonymous front-end visitors get a 503 with a
     * `Retry-After`, and admin, cron, AJAX and REST contexts are never intercepted.
     *
     * @return array{
     *     mode: string,
     *     confirmation: string,
     *     summary: string,
     *     consequences: list<string>
     * }
     */
    public function describe(string $mode): array
    {
        $mode = $this->modes->normalize($mode);

        return [
            'mode'         => $mode,
            'confirmation' => $this->confirmationFor($mode),
            'summary'      => $this->summary($mode),
            'consequences' => $this->consequences($mode),
        ];
    }

    /**
     * Every mode's description, for the renderer that draws all of them and hides the inactive ones.
     *
     * @return list<array{mode:string,confirmation:string,summary:string,consequences:list<string>}>
     */
    public function describeAll(): array
    {
        return array_map(
            fn (string $mode): array => $this->describe($mode),
            $this->modes->all(),
        );
    }

    private function summary(string $mode): string
    {
        return match ($mode) {
            OperationsMode::DEVELOPMENT => __(
                'For building. Debugging aids may be visible, and the site is not presented as finished.',
                'corex',
            ),
            OperationsMode::STAGING => __(
                'For review. The site behaves like production but is not the live one.',
                'corex',
            ),
            OperationsMode::PRODUCTION => __(
                'Live. Real visitors, real data, and real consequences for mistakes.',
                'corex',
            ),
            OperationsMode::MAINTENANCE => __(
                'Closed to visitors while you work. Administrators keep working normally.',
                'corex',
            ),
            default => '',
        };
    }

    /**
     * @return list<string>
     */
    private function consequences(string $mode): array
    {
        return match ($mode) {
            OperationsMode::DEVELOPMENT => [
                __('The site remains publicly reachable — this mode does not hide it.', 'corex'),
                __('Readiness blockers are reported but not enforced.', 'corex'),
            ],
            OperationsMode::STAGING => [
                __('Search engines should be discouraged from indexing this site.', 'corex'),
                __('External services still receive real requests unless you have pointed them elsewhere.', 'corex'),
            ],
            OperationsMode::PRODUCTION => [
                __('Readiness blockers must be resolved, or overridden deliberately by typing the confirmation phrase.', 'corex'),
                __('The change is recorded in the mode history with your name against it.', 'corex'),
            ],
            OperationsMode::MAINTENANCE => [
                __('Visitors who are not signed in receive a maintenance page with a 503 status.', 'corex'),
                __('Signed-in administrators continue to use the site normally.', 'corex'),
                __('The REST API, AJAX, cron, and wp-admin are never intercepted.', 'corex'),
                __('Recovery: change the mode back here, or run the recovery command if you cannot reach this screen.', 'corex'),
            ],
            default => [],
        };
    }
}
