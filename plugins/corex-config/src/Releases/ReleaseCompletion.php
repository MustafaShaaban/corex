<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * The end of an installation, done by the release that was just put in place (spec 107, US3;
 * plan D8 and D19; FR-030, FR-032, FR-035).
 *
 * The files are the new release's. This brings the database to it, and only then says the
 * release is installed: records it as the site's, lets visitors in, removes what the
 * installation unpacked and the package it came in, and writes it down.
 *
 * It asks for no key. The key opens WordPress's maintenance answer, which this request has
 * already passed or which has ended by itself. So an installation whose browser went away is
 * finished by whoever next opens the Releases screen and may install releases. What it writes
 * down says how this request got in (`ReleaseMaintenance::keyCameFrom()`): through the gate,
 * and with the key from where, or after maintenance had ended.
 */
final class ReleaseCompletion
{
    public const NOT_READY = 'not_in_place';

    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ReleaseFinisher $finisher,
        private readonly InstalledRelease $installed,
        private readonly ReleaseStaging $staging,
        private readonly ReleaseSafeguards $safeguards,
        private readonly ReleaseJournal $journal,
        private readonly ReleaseRecord $record,
    ) {
    }

    /**
     * Do the database step, or the next part of it on a network, and finish when it is done.
     *
     * @return array<string,mixed> When finished: `done`, the `version` now installed, and the `changes` made to the database. Before that: where the installation stands.
     *
     * @throws ReleaseRefused When no installation has its files in place, or a database change failed. A failure is on record, the release is not recorded as installed, and visitors are still held off.
     */
    public function finish(int $actorId, DateTimeImmutable $now): array
    {
        return $this->store->exclusively(function () use ($actorId, $now): array {
            $progress = $this->inPlace();

            try {
                $moved = $this->finisher->run($progress->cursor);
            } catch (ReleaseRefused $refused) {
                $this->stopOn($refused, $progress, $actorId, $now);

                throw $refused;
            }

            $progress = $progress->with([
                'step'    => ReleaseProgress::FINISH,
                'cursor'  => $moved['next'],
                'changes' => [...$progress->changes, ...$moved['changes']],
                'failed'  => null,
            ]);

            if (! $moved['done']) {
                // More sites of a network to bring to the release: visitors stay held off.
                $this->safeguards->hold($progress->keyHash, $now->getTimestamp());
                $progress->save($this->store);

                return [...$progress->forTheScreen(), 'done' => false];
            }

            return $this->close($progress, $actorId, $now);
        });
    }

    /**
     * The installation whose files are in place.
     *
     * One that still says it stands at the swap, with a journal that says the swap was made,
     * is one whose swapping request moved the last folder and did not live to say so.
     *
     * @throws ReleaseRefused
     */
    private function inPlace(): ReleaseProgress
    {
        $progress = ReleaseProgress::standing($this->store)
            ?? throw new ReleaseRefused(ReleaseInstallation::NONE, __('No installation is in progress on this site.', 'corex'));

        $swapped = $progress->step === ReleaseProgress::SWAP
            && $this->journal->status() === ReleaseJournal::SWAPPED
            && ($this->journal->about()['for'] ?? null) === $progress->keyHash;

        if ($progress->step !== ReleaseProgress::FINISH && ! $swapped) {
            throw new ReleaseRefused(self::NOT_READY, __('The files of this release are not in place yet, so its database step cannot run.', 'corex'));
        }

        return $progress;
    }

    /**
     * @return array<string,mixed>
     */
    private function close(ReleaseProgress $progress, int $actorId, DateTimeImmutable $now): array
    {
        $manifest = $progress->manifest();

        $this->installed->record($manifest->described());
        $this->safeguards->lower();
        $this->staging->clear();
        $this->removeThePackage($progress->package);
        $this->store->clearState();
        $this->record->write(ReleaseEvent::of(ReleaseEvent::INSTALLED, $progress, [
            'changes'  => count($progress->changes),
            'key_from' => ReleaseMaintenance::keyCameFrom(),
        ]), $actorId, $now);

        return ['done' => true, 'version' => $manifest->version, 'changes' => $progress->changes];
    }

    /** A package that has been installed is not kept (FR-003). */
    private function removeThePackage(string $package): void
    {
        $file = $this->store->fileIn('incoming', $package);

        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * A database change failed. The installation stays where it is, saying why, so the screen
     * can say it again and offer the way back. It is written down once, not at every retry.
     */
    private function stopOn(ReleaseRefused $refused, ReleaseProgress $progress, int $actorId, DateTimeImmutable $now): void
    {
        if ($progress->failed === null) {
            $this->record->write(ReleaseEvent::of(ReleaseEvent::FAILED, $progress, [
                'reason'   => $refused->reason,
                'key_from' => ReleaseMaintenance::keyCameFrom(),
            ]), $actorId, $now);
        }

        $progress->with(['step' => ReleaseProgress::FINISH, 'failed' => $refused->getMessage()])->save($this->store);
    }
}
