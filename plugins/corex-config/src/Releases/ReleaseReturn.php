<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Going back to the release before (spec 107, US4; FR-040 to FR-045; plan D9, D10, D19).
 *
 * The folders the last installation moved out are moved back, by the swap's own journal read
 * backwards. That is all of it: no change to the database is reversed, and the copies of the
 * tables are left for a person to read from. The release gone back to runs on the tables as
 * the newer one left them.
 *
 * There are two ways back and they end the same. From the Releases screen, which is this. And
 * through the recovery link, which runs without CoreX and so cannot tell CoreX it did: the
 * screen finds that out when it is next opened, and puts its record right.
 */
final class ReleaseReturn
{
    public const NOTHING_KEPT  = 'nothing_kept';
    public const NOT_CONFIRMED = 'return_not_confirmed';
    public const INSTALLING    = 'installation_in_the_way';
    public const HALF_DONE     = 'return_half_done';

    /** In the record, the version of a release the site could not name. */
    public const UNNAMED = 'unknown';

    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ReleaseSwap $swap,
        private readonly ReleaseJournal $journal,
        private readonly ReleaseStaging $staging,
        private readonly ReleaseSafeguards $safeguards,
        private readonly InstalledRelease $installed,
        private readonly ReleaseTableCopies $copies,
        private readonly ReleaseRecord $record,
        private readonly ReleaseSteps $steps,
    ) {
    }

    /**
     * The release that can be gone back to, or none.
     *
     * @return array{status:string,version:?string,replaced_by:string,at:string,phrase:string,copies:list<array{table:string,copy:string,made_at:string,goes_at:string}>,copies_days:int}|null
     *         `status` is `kept`, or `stuck` when an earlier attempt left folders where they do
     *         not belong. `version` is null for a release the site could not name. `phrase` is
     *         what is typed to confirm going back.
     */
    public function kept(): ?array
    {
        // Asked when the screen opens, on a host where the installer's place may not exist.
        if (! $this->store->exists()) {
            return null;
        }

        $status = $this->journal->status();
        $about  = $this->journal->about();

        if (! in_array($status, [ReleaseJournal::SWAPPED, ReleaseJournal::STUCK], true) || ! isset($about['installed']['corex_version'])) {
            return null;
        }

        $version    = self::versionOf($about['replaced'] ?? null);
        $replacedBy = (string) $about['installed']['corex_version'];

        return [
            'status'      => $status === ReleaseJournal::SWAPPED ? 'kept' : 'stuck',
            'version'     => $version,
            'replaced_by' => $replacedBy,
            'at'          => (string) ($about['at'] ?? ''),
            // The release gone back to, where the site can name it; else the one being left.
            'phrase'      => $version ?? $replacedBy,
            'copies'      => $this->copies->kept(),
            'copies_days' => ReleaseTableCopies::DAYS,
        ];
    }

    /**
     * Put the previous release's folders back.
     *
     * @param string $phrase What the administrator typed to confirm it.
     *
     * @return array{version:?string,left:string,database_changes_reversed:int} The release the site is back on, the one it left, and how many database changes were reversed: none, said as a number so it is never left unsaid.
     *
     * @throws ReleaseRefused When there is nothing to go back to, it is not confirmed, an installation that has moved nothing is in the way, or a folder would not go back.
     */
    public function goBack(string $phrase, int $actorId, DateTimeImmutable $now): array
    {
        return $this->store->exclusively(function () use ($phrase, $actorId, $now): array {
            $this->refuseOverAnInstallationThatHasMovedNothing();

            $kept = $this->kept() ?? throw new ReleaseRefused(
                self::NOTHING_KEPT,
                __('There is no previous release to go back to on this site.', 'corex'),
            );

            if (! hash_equals($kept['phrase'], trim($phrase))) {
                throw new ReleaseRefused(self::NOT_CONFIRMED, sprintf(
                    /* translators: %s: a release's version, e.g. 0.44.0. */
                    __('Not confirmed. Type %s exactly to go back.', 'corex'),
                    $kept['phrase'],
                ));
            }

            $about = $this->journal->about();
            $this->swap->undo();

            if ($this->journal->status() !== ReleaseJournal::UNDONE) {
                throw new ReleaseRefused(
                    self::HALF_DONE,
                    __('A folder would not go back, so the site is part one release and part the other. The recovery link puts the rest back; so does moving each folder back by hand, as the journal in wp-content/corex-releases/previous lists them.', 'corex'),
                );
            }

            $this->settle($about, $actorId, $now, []);

            return ['version' => $kept['version'], 'left' => $kept['replaced_by'], 'database_changes_reversed' => 0];
        });
    }

    /**
     * Take in a return that was made through the recovery link (FR-045).
     *
     * The link's plugin moved the folders back and marked the journal, and could do no more:
     * it uses nothing of CoreX. A journal that says "undone" beside a recovery plugin that is
     * still there is that. A return made from the screen removes the plugin.
     *
     * @return bool Whether there was one to take in.
     */
    public function takeInOneMadeByTheLink(DateTimeImmutable $now): bool
    {
        $about = $this->journal->about();

        if ($this->journal->status() !== ReleaseJournal::UNDONE || ! $this->safeguards->hasAWayBack() || ! isset($about['installed'])) {
            return false;
        }

        // Nobody is known to have done it: whoever had the link did.
        $this->settle($about, 0, $now, ['through' => 'recovery_link']);

        return true;
    }

    /**
     * An installation that is unpacking, checking, or stopped at the swap before a folder moved
     * has changed nothing, and its staged folders are where a return puts the release it moves
     * out. It is given up first.
     *
     * @throws ReleaseRefused
     */
    private function refuseOverAnInstallationThatHasMovedNothing(): void
    {
        $standing = ReleaseProgress::standing($this->store);

        if ($standing !== null && $standing->step !== ReleaseProgress::AWAITING && $this->steps->hasMovedNothing($standing)) {
            throw new ReleaseRefused(
                self::INSTALLING,
                __('An installation is in progress and has not moved anything yet. Give it up first, then go back.', 'corex'),
            );
        }
    }

    /**
     * The folders are back. Make everything else say so: let visitors in, remove the way back
     * there is no more use for, record the release the site is on again, remove the newer
     * release's folders and what stood of its installation, and write it down.
     *
     * @param array<string,mixed>  $about   What the journal kept of the swap that was undone.
     * @param array<string,scalar> $details What else the record keeps of this return.
     */
    private function settle(array $about, int $actorId, DateTimeImmutable $now, array $details): void
    {
        $replaced = is_array($about['replaced'] ?? null) ? $about['replaced'] : null;

        $this->safeguards->remove();
        $replaced === null ? $this->installed->forget() : $this->installed->record($replaced);
        $this->staging->clear();
        $this->store->clearState();
        $this->record->write(
            new ReleaseEvent(
                ReleaseEvent::RETURNED,
                self::versionOf($replaced) ?? self::UNNAMED,
                self::versionOf($about['installed'] ?? null),
                $details,
            ),
            $actorId,
            $now,
        );
    }

    private static function versionOf(mixed $description): ?string
    {
        $version = is_array($description) ? ($description['corex_version'] ?? null) : null;

        return is_string($version) && $version !== '' ? $version : null;
    }
}
