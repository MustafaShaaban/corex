<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * Work on the rows a schema component's tables already hold, done when the component migrates.
 *
 * A version can need stored rows to change, not only the tables: spec 124 rewrites the lockout
 * notices an earlier release kept the typed username and the address in (FR-020). A step runs on
 * every path a site is migrated by (the admin's self-heal, `wp corex migrate`, a release installed
 * from the admin, a new site), in the site's scope, after the component's tables exist and before
 * its version is stored; one that throws leaves the version unwritten, so it is tried again.
 *
 * A step runs each time its component's version changes, so it MUST be idempotent: it finds the rows
 * that still need it and leaves the rest, and a second run changes nothing. It MUST work in batches,
 * so a site with many rows is not read into memory at once.
 */
interface DataMigrationStep
{
    /** A short name for the step, used in the failure a site reports. */
    public function id(): string;

    /** Brings the site in scope's stored rows to the component's version. */
    public function run(): void;
}
