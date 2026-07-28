<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Application;

defined('ABSPATH') || exit;

use Corex\Data\DataField;
use Corex\Database\Schema\ManagedTable;
use Corex\Database\Schema\Table;

/**
 * The applications table, and its declaration to the Data screen (spec 081, #138 item 14).
 *
 * The table was created and migrated from the day the add-on shipped, and never registered as
 * managed — so every application a site received went into a table no admin surface reads. Combined
 * with the CV that was never stored, a careers page could collect applications that nobody could
 * see and that contained no attachment.
 *
 * Mirrors `Newsletter\Subscriber\SubscriberTable`, which is the framework's reference for this.
 */
final class ApplicationTable
{
    public const NAME = 'applications';

    public function schema(): Table
    {
        return (new Table(self::NAME))
            ->id()->integer('job_id')->string('name')->string('email')->text('cover_letter')
            ->integer('cv_attachment')->string('status', 20)->timestamps();
    }

    public function managed(): ManagedTable
    {
        return new ManagedTable(
            self::NAME,
            'Job applications',
            [
                ['id' => 'name', 'label' => 'Applicant'],
                ['id' => 'email', 'label' => 'Email'],
                ['id' => 'job_id', 'label' => 'Job'],
                ['id' => 'cv_attachment', 'label' => 'CV'],
                ['id' => 'status', 'label' => 'Status'],
                ['id' => 'created_at', 'label' => 'Applied'],
            ],
            'corex',
            [
                [
                    'id'   => 'cv_attachment',
                    // The type is what makes the admin render a link to the file rather than the
                    // bare integer that the id is (#138 item 6). Declared here because the table
                    // is the only place that knows this column holds an attachment.
                    'type' => DataField::TYPE_ATTACHMENT,
                ],
                [
                    'id'         => 'status',
                    'type'       => 'text',
                    'validation' => ['max_length' => 20],
                ],
            ],
            // Applications carry somebody's name, email and CV. A column nobody declared is more
            // likely a mis-picked export than a value worth guessing at, and this is the last place
            // to guess.
            ManagedTable::UNKNOWN_REJECT,
        );
    }
}
