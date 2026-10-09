<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * What the Releases screen asks before anything is installed: what is on this site, what this
 * host can do, and what a given package is (spec 107, US1 and US2; FR-010, FR-017).
 *
 * It reads, until a package is refused. Then it writes a line in the installer's log (FR-051:
 * who offered it, when, and why it was not taken) and removes the package: one the site will
 * not install is not left on it (FR-003).
 */
final class ReleaseDesk
{
    public const NOT_THERE = 'not_there';

    /** The least and the most a part of an upload is, whatever the host says it takes. */
    private const SMALLEST_PART = 256 * 1024;

    private const LARGEST_PART = 4 * 1024 * 1024;

    /**
     * @param list<string> $folders The folders an installation writes in; the first is where releases are kept.
     */
    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ReleasePackageInspector $inspector,
        private readonly InstalledRelease $installed,
        private readonly array $folders,
    ) {
    }

    /**
     * What the screen shows when it is opened.
     *
     * @return array{installed:array{known:bool,version:?string,client:?string},blockers:list<array{reason:string,message:string}>,packages:list<array{name:string,bytes:int}>,part_bytes:int}
     */
    public function overview(): array
    {
        return [
            'installed'  => [
                'known'   => $this->installed->isKnown(),
                'version' => $this->installed->version(),
                'client'  => $this->installed->client(),
            ],
            'blockers'   => ReleaseHostFacts::ofThisHost($this->folders)->blockers(0),
            'packages'   => $this->store->packages(),
            'part_bytes' => $this->partBytes(),
        ];
    }

    /**
     * What a package on the site is, or why it is refused.
     *
     * @param string $name    The package's name in `incoming/`.
     * @param int    $actorId Who asked: recorded with a refusal.
     *
     * @return array<string,mixed> The statement of FR-010, and what on this host stands in the way of installing it.
     *
     * @throws ReleaseRefused When the package is not one this site should install. It is gone from the site by then.
     */
    public function inspect(string $name, int $actorId): array
    {
        $file = $this->store->fileIn('incoming', $name);

        if (! is_file($file)) {
            throw new ReleaseRefused(
                self::NOT_THERE,
                __('That package is not on the site any more. Upload it again.', 'corex'),
            );
        }

        try {
            $inspection = $this->inspector->inspect($file);
        } catch (ReleaseRefused $refused) {
            $this->store->record([
                'event'   => 'refused',
                'package' => $name,
                'reason'  => $refused->reason,
                'by'      => $actorId,
                'at'      => gmdate('c'),
            ]);
            unlink($file);

            throw new ReleaseRefused($refused->reason, sprintf(
                /* translators: %s: why a package was refused, as one or more whole sentences. */
                __('%s The package was removed from the site.', 'corex'),
                $refused->getMessage(),
            ));
        }

        return [
            'package'            => $name,
            'version'            => $inspection->manifest->version,
            'built_at'           => $inspection->manifest->builtAt->format(DATE_ATOM),
            'client'             => $inspection->manifest->client,
            'replaces'           => $inspection->replaces,
            'older'              => $inspection->older,
            'client_unconfirmed' => $inspection->clientUnconfirmed,
            'files'              => $inspection->files,
            'bytes'              => $inspection->bytes,
            // A release is unpacked beside the one it replaces before either is moved.
            'blockers'           => ReleaseHostFacts::ofThisHost($this->folders)->blockers($inspection->bytes),
        ];
    }

    /**
     * How much of a package the browser sends at a time: half of what the host says it takes
     * in one upload, so the request's own overhead and a proxy's lower limit both fit, and never
     * so small that a package is hundreds of requests or so large that one lost part is minutes.
     */
    private function partBytes(): int
    {
        return (int) max(self::SMALLEST_PART, min(self::LARGEST_PART, intdiv((int) wp_max_upload_size(), 2)));
    }
}
