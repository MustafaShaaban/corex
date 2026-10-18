<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use Corex\Operations\Confirmation;
use DateTimeImmutable;

/**
 * An installation, from the package an administrator chose to the release being in place (spec
 * 107, US1; plan D8; FR-015, FR-018, FR-020, FR-023, FR-050).
 *
 * It is asked for a piece at a time, each a request the browser makes. `prepare` states what
 * would be installed and gives the administrator the way back. `confirm` takes the version
 * typed. `step` does the next piece until the release is in place. `cancel` gives up one that
 * has not moved anything. What comes after the files are in place is `ReleaseCompletion`,
 * which the release just installed answers.
 *
 * One at a time: each of these holds the installer's lock, and a prepared or running
 * installation refuses another.
 */
final class ReleaseInstallation
{
    public const IN_PROGRESS     = 'installation_in_progress';
    public const BLOCKED         = 'host_cannot_install';
    public const NOT_CONFIRMED   = 'not_confirmed';
    public const NONE            = 'no_installation';
    public const PAST_CANCELLING = 'past_cancelling';

    private const OPERATION = 'release.install';

    /**
     * @param string $siteRoot The folder a release's paths are relative to.
     */
    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ReleaseDesk $desk,
        private readonly InstalledRelease $installed,
        private readonly ReleaseSteps $steps,
        private readonly ReleaseSafeguards $safeguards,
        private readonly ReleaseRecord $record,
        private readonly string $siteRoot,
    ) {
    }

    /**
     * State what installing a package would do, and give the way back, before anything is asked for.
     *
     * Nothing of the site changes. The key in the answer is made here and kept nowhere: the
     * browser holds it for the installation, and the administrator is given the recovery link
     * it is part of.
     *
     * @param string $package The package's name in `incoming/`.
     *
     * @return array<string,mixed> The statement of FR-010; `drops`, the folders the running release holds and this one does not; `key` and `recovery_link`; `copies_days`, how long the table copies are kept.
     *
     * @throws ReleaseRefused When the package is refused, the host cannot install, or an installation is in progress.
     */
    public function prepare(string $package, int $actorId, DateTimeImmutable $now): array
    {
        return $this->store->exclusively(function () use ($package, $actorId, $now): array {
            $this->refuseBesideAnother($actorId, $now);

            $inspection = $this->desk->examine($package, $actorId);
            $statement  = $this->desk->statementOf($package, $inspection);
            $this->refuseWhereTheHostCannot($statement['blockers']);

            $key      = bin2hex(random_bytes(32));
            $progress = new ReleaseProgress(
                step: ReleaseProgress::AWAITING,
                cursor: 0,
                of: 0,
                package: $package,
                packageHash: (string) hash_file('sha256', $this->store->fileIn('incoming', $package)),
                manifest: $inspection->manifest->json,
                keyHash: hash('sha256', $key),
                by: $actorId,
                preparedAt: $now->format(DATE_ATOM),
                older: $inspection->older,
                clientUnconfirmed: $inspection->clientUnconfirmed,
                replaced: $this->installed->description(),
                downgrades: $inspection->downgrades,
            );
            $progress->save($this->store);

            return [
                ...$statement,
                'drops'         => $this->dropped($inspection->manifest),
                'key'           => $key,
                'recovery_link' => $this->safeguards->link($key),
                'copies_days'   => ReleaseTableCopies::DAYS,
            ];
        });
    }

    /**
     * Take the administrator's confirmation: the version, typed.
     *
     * @return array<string,mixed> Where the installation stands.
     *
     * @throws ReleaseRefused When there is nothing to confirm, or it is not confirmed.
     */
    public function confirm(ReleaseConfirmationGiven $given, int $actorId, DateTimeImmutable $now): array
    {
        return $this->store->exclusively(function () use ($given, $actorId, $now): array {
            $progress = $this->standing();

            if ($progress->step !== ReleaseProgress::AWAITING) {
                throw new ReleaseRefused(self::IN_PROGRESS, __('This installation was confirmed already and is under way.', 'corex'));
            }

            $this->refuseUnlessConfirmed($progress, $given, $actorId, $now);

            $progress = $progress->at(ReleaseProgress::UNPACK);
            $progress->save($this->store);

            return $progress->forTheScreen();
        });
    }

    /**
     * Do the next piece of the installation.
     *
     * @return array<string,mixed> Where it stands afterwards. At `finish`, the files are the new release's and the next request is `ReleaseCompletion`'s.
     *
     * @throws ReleaseRefused When there is none, it is not confirmed, or a step could not be done. An installation that could not be done is over, and on record, by the time this is thrown.
     */
    public function step(int $actorId, DateTimeImmutable $now): array
    {
        return $this->store->exclusively(function () use ($actorId, $now): array {
            $progress = $this->standing();

            if ($progress->step === ReleaseProgress::AWAITING) {
                throw new ReleaseRefused(self::NOT_CONFIRMED, __('This installation has not been confirmed. Type the version it installs to confirm it.', 'corex'));
            }

            try {
                $progress = $this->steps->next($progress, $now);
            } catch (ReleaseRefused $refused) {
                $this->endOn($refused, $progress, $actorId, $now);

                throw $refused;
            }

            $progress->save($this->store);

            return $progress->forTheScreen();
        });
    }

    /**
     * The installation in progress as the screen is told of it, with whether it can be given up;
     * or none.
     *
     * @return array<string,mixed>|null `ReleaseProgress::forTheScreen()`, and `moved_nothing`.
     *
     * @throws ReleaseRefused When one was started by a release whose record this one cannot read.
     */
    public function standingForTheScreen(): ?array
    {
        $progress = ReleaseProgress::standing($this->store);

        return $progress === null ? null : [...$progress->forTheScreen(), 'moved_nothing' => $this->steps->hasMovedNothing($progress)];
    }

    /**
     * Give up an installation that has not moved anything of the site: before the swap, or at it
     * before its first folder moved (FR-022a).
     *
     * @throws ReleaseRefused When there is none, or it has moved a folder: the way out of that is going on, or going back.
     */
    public function cancel(int $actorId, DateTimeImmutable $now): void
    {
        $this->store->exclusively(function () use ($actorId, $now): void {
            $progress = $this->standing();

            if (! $this->steps->hasMovedNothing($progress)) {
                throw new ReleaseRefused(
                    self::PAST_CANCELLING,
                    __('This installation has already moved folders of the site, so it can no longer be given up. Go on with it, or go back to the previous release.', 'corex'),
                );
            }

            $this->steps->abandon($progress);
            $this->store->clearState();

            // One that was only ever looked at is not an event.
            if ($progress->step !== ReleaseProgress::AWAITING) {
                $this->record->write(ReleaseEvent::of(ReleaseEvent::CANCELLED, $progress), $actorId, $now);
            }
        });
    }

    /**
     * @throws ReleaseRefused
     */
    private function standing(): ReleaseProgress
    {
        return ReleaseProgress::standing($this->store)
            ?? throw new ReleaseRefused(self::NONE, __('No installation is in progress on this site.', 'corex'));
    }

    /**
     * Asked where an installation starts: what the screen showed when it opened is not what
     * keeps a site that may not change its files from changing (FR-018).
     *
     * @param list<array{reason:string,message:string}> $blockers
     *
     * @throws ReleaseRefused
     */
    private function refuseWhereTheHostCannot(array $blockers): void
    {
        if ($blockers !== []) {
            throw new ReleaseRefused(self::BLOCKED, implode(' ', array_column($blockers, 'message')));
        }
    }

    /**
     * One installation at a time. A prepared one that was never confirmed gives way to its own
     * administrator starting again, and to anybody once its time to be confirmed has passed.
     *
     * @throws ReleaseRefused
     */
    private function refuseBesideAnother(int $actorId, DateTimeImmutable $now): void
    {
        $standing = ReleaseProgress::standing($this->store);

        if ($standing === null) {
            return;
        }

        $onlyPrepared = $standing->step === ReleaseProgress::AWAITING;

        if ($onlyPrepared && ($standing->by === $actorId || $now >= $standing->waitsUntil())) {
            return;
        }

        throw new ReleaseRefused(self::IN_PROGRESS, sprintf(
            /* translators: 1: a release's version. 2: a date and time. */
            __('An installation of %1$s is in progress on this site, started %2$s. Go on with it or cancel it from this screen before starting another.', 'corex'),
            $standing->manifest()->version,
            wp_date(get_option('date_format') . ' ' . get_option('time_format'), (new DateTimeImmutable($standing->preparedAt))->getTimestamp()),
        ));
    }

    /**
     * The confirmation of plan D8: this administrator, this package as it was when it was
     * stated, the version typed, in time. Used once: a confirmed installation is no longer one
     * that awaits it.
     *
     * @throws ReleaseRefused
     */
    private function refuseUnlessConfirmed(ReleaseProgress $progress, ReleaseConfirmationGiven $given, int $actorId, DateTimeImmutable $now): void
    {
        $manifest     = $progress->manifest();
        $confirmation = new Confirmation(self::OPERATION, $progress->packageHash, $progress->by, $progress->waitsUntil(), $manifest->version);
        // A package that is gone hashes to nothing, which is not the hash that was stated.
        $packageNow = (string) @hash_file('sha256', $this->store->fileIn('incoming', $progress->package)); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        if (! $confirmation->verify(self::OPERATION, $packageNow, $actorId, trim($given->phrase), $now)) {
            throw new ReleaseRefused(self::NOT_CONFIRMED, sprintf(
                /* translators: %s: a release's version, e.g. 0.45.0. */
                __('Not confirmed. Type the version being installed, %s, exactly. A confirmation is good for fifteen minutes and for the administrator who asked for it: if it has passed, choose the package again.', 'corex'),
                $manifest->version,
            ));
        }

        if ($progress->older && ! $given->olderAccepted) {
            throw new ReleaseRefused(self::NOT_CONFIRMED, __('This is an older release than the one the site runs. Say that an older release is meant, and confirm again.', 'corex'));
        }

        if ($progress->downgrades !== [] && ! $given->downgradeAccepted) {
            throw new ReleaseRefused(self::NOT_CONFIRMED, __('This package holds older versions of plugins or themes than the site has. Say that they are meant to go back to those versions, and confirm again.', 'corex'));
        }

        if ($progress->clientUnconfirmed && $manifest->client !== null && trim((string) $given->client) !== $manifest->client) {
            throw new ReleaseRefused(self::NOT_CONFIRMED, __('This site does not know which client it belongs to. Type the client the package was built for, exactly as the statement gives it, to confirm it is this site\'s.', 'corex'));
        }
    }

    /**
     * The folders the running release recorded as its own that this package does not hold,
     * and that are on the site: installing moves each out with the previous release (FR-028).
     *
     * @return list<string>
     */
    private function dropped(ReleaseManifest $manifest): array
    {
        return array_values(array_filter(
            array_diff($this->installed->ownedFolders(), $manifest->releasePaths),
            fn (string $path): bool => is_dir(rtrim($this->siteRoot, '/\\') . '/' . $path),
        ));
    }

    /**
     * A step could not be done. Unless folders are left where they do not belong, the
     * installation is over and the site is as it was.
     */
    private function endOn(ReleaseRefused $refused, ReleaseProgress $progress, int $actorId, DateTimeImmutable $now): void
    {
        if ($refused->reason === ReleaseSwap::HALF_DONE) {
            $progress->at(ReleaseProgress::STUCK)->save($this->store);
        } else {
            $this->steps->abandon($progress);
            $this->store->clearState();
        }

        $this->record->write(ReleaseEvent::of(ReleaseEvent::FAILED, $progress, ['reason' => $refused->reason]), $actorId, $now);
    }
}
