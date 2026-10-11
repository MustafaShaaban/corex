<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * A source that says which of the Data screen's two groups it is listed in (spec 109, FR-003).
 *
 * Choosing several models to export is choosing from a list, and a list of eleven that mixes a
 * site's leads with its job log is read every time. The two groups are the records a site
 * receives or keeps for its work, and the site's own logs. A source that does not say is listed
 * with the records.
 */
interface CategorisedDataSource
{
    public const RECORDS = 'records';
    public const LOG = 'log';

    /** One of `RECORDS`, `LOG`. */
    public function category(): string;
}
