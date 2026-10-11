<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use DateInterval;
use DateTimeImmutable;

/**
 * Where an installation stands: `state.json` in the installer's place, read and written as one
 * thing (spec 107, plan D5 and D8; FR-022, FR-023).
 *
 * An installation is many requests, and the release that starts it is not the release that
 * finishes it: the requests after the swap are answered by the code just installed. So this
 * shape is a contract between releases. `PROTOCOL` names it. A release that changes what is
 * kept here raises the number, and must go on reading the one before it, because that is what
 * the release it replaces will have written.
 *
 * The installation's key is not here. Its hash is, to write into the two files that ask for it.
 */
final readonly class ReleaseProgress
{
    public const PROTOCOL = 1;

    /** Prepared and shown to an administrator, who has not confirmed it yet. */
    public const AWAITING = 'awaiting';

    public const UNPACK      = 'unpack';
    public const VERIFY      = 'verify';
    public const COPY_TABLES = 'copy_tables';
    public const SWAP        = 'swap';

    /** The files are the new release's; its database step has not completed. */
    public const FINISH = 'finish';

    /** A folder would neither move nor go back. The site is held in maintenance; the recovery link is the way out. */
    public const STUCK = 'stuck';

    public const UNREADABLE = 'installation_unreadable';

    /** How long a prepared installation waits to be confirmed. */
    private const WAITS = 'PT15M';

    private const BEFORE_THE_SWAP = [self::AWAITING, self::UNPACK, self::VERIFY, self::COPY_TABLES];

    /**
     * @param string                                                            $step              One of this class's steps: the one the next request does.
     * @param int                                                               $cursor            How far into that step earlier requests got.
     * @param int                                                               $of                How much the step has in all, once it is known; 0 before.
     * @param string                                                            $package           The package's name in `incoming/`.
     * @param string                                                            $packageHash       The SHA-256 of the package as it was when prepared.
     * @param string                                                            $manifest          The package's description, as its JSON.
     * @param string                                                            $keyHash           The SHA-256 of the installation's key.
     * @param int                                                               $by                Who prepared it.
     * @param string                                                            $preparedAt        When, as ISO 8601.
     * @param bool                                                              $older             The package is an older release than the one running.
     * @param bool                                                              $clientUnconfirmed The site could not say whose it is, so the client is confirmed by name.
     * @param array<string,mixed>|null                                          $replaced          The description of the release that was running, where the site had one.
     * @param list<array{site:int,component:string,from:string,to:string}>      $changes           The database changes made so far.
     * @param string|null                                                       $failed            Why the database step stopped, when it did.
     */
    public function __construct(
        public string $step,
        public int $cursor,
        public int $of,
        public string $package,
        public string $packageHash,
        public string $manifest,
        public string $keyHash,
        public int $by,
        public string $preparedAt,
        public bool $older,
        public bool $clientUnconfirmed,
        public ?array $replaced,
        public array $changes = [],
        public ?string $failed = null,
    ) {
    }

    /**
     * The installation in progress on this site, or none.
     *
     * @throws ReleaseRefused When one was started by a release whose record this one cannot read.
     */
    public static function standing(ReleaseStore $store): ?self
    {
        $state = $store->state();

        if ($state === []) {
            return null;
        }

        if (($state['protocol'] ?? null) !== self::PROTOCOL) {
            throw new ReleaseRefused(
                self::UNREADABLE,
                __('An installation was started on this site by a release whose record this one cannot read. Use the recovery link you were given to put the previous release back, then install again.', 'corex'),
            );
        }

        return new self(
            (string) $state['step'],
            (int) $state['cursor'],
            (int) $state['of'],
            (string) $state['package'],
            (string) $state['package_hash'],
            (string) $state['manifest'],
            (string) $state['key_hash'],
            (int) $state['by'],
            (string) $state['prepared_at'],
            (bool) $state['older'],
            (bool) $state['client_unconfirmed'],
            is_array($state['replaced'] ?? null) ? $state['replaced'] : null,
            array_values((array) ($state['changes'] ?? [])),
            is_string($state['failed'] ?? null) ? $state['failed'] : null,
        );
    }

    public function save(ReleaseStore $store): void
    {
        $store->saveState([
            'protocol'           => self::PROTOCOL,
            'step'               => $this->step,
            'cursor'             => $this->cursor,
            'of'                 => $this->of,
            'package'            => $this->package,
            'package_hash'       => $this->packageHash,
            'manifest'           => $this->manifest,
            'key_hash'           => $this->keyHash,
            'by'                 => $this->by,
            'prepared_at'        => $this->preparedAt,
            'older'              => $this->older,
            'client_unconfirmed' => $this->clientUnconfirmed,
            'replaced'           => $this->replaced,
            'changes'            => $this->changes,
            'failed'             => $this->failed,
        ]);
    }

    /** The same installation, standing at another step or further into this one. */
    public function at(string $step, int $cursor = 0, int $of = 0): self
    {
        return $this->with(['step' => $step, 'cursor' => $cursor, 'of' => $of]);
    }

    /**
     * The same installation with some of it changed, by the constructor's own names.
     *
     * @param array<string,mixed> $changed
     */
    public function with(array $changed): self
    {
        return new self(...[...get_object_vars($this), ...$changed]);
    }

    public function manifest(): ReleaseManifest
    {
        return ReleaseManifest::fromJson($this->manifest);
    }

    /** The version the site ran before, where it knew. */
    public function replacedVersion(): ?string
    {
        $version = $this->replaced['corex_version'] ?? null;

        return is_string($version) ? $version : null;
    }

    public function isBeforeTheSwap(): bool
    {
        return in_array($this->step, self::BEFORE_THE_SWAP, true);
    }

    /** When a prepared installation stops waiting to be confirmed. */
    public function waitsUntil(): DateTimeImmutable
    {
        return (new DateTimeImmutable($this->preparedAt))->add(new DateInterval(self::WAITS));
    }

    /**
     * What the screen is told of it: where it stands and whose it is. Nothing a browser could
     * use to pass for the installation.
     *
     * @return array{step:string,cursor:int,of:int,version:string,package:string,by:int,prepared_at:string,before_the_swap:bool,failed:?string}
     */
    public function forTheScreen(): array
    {
        return [
            'step'            => $this->step,
            'cursor'          => $this->cursor,
            'of'              => $this->of,
            'version'         => $this->manifest()->version,
            'package'         => $this->package,
            'by'              => $this->by,
            'prepared_at'     => $this->preparedAt,
            'before_the_swap' => $this->isBeforeTheSwap(),
            'failed'          => $this->failed,
        ];
    }
}
