<?php

/**
 * @package Corex\Newsletter
 */

declare(strict_types=1);

namespace Corex\Newsletter\Subscriber;

defined('ABSPATH') || exit;

use Corex\Database\Schema\ManagedTable;
use Corex\Database\Schema\Table;

/**
 * The subscribers table, and what CoreX may do to it (spec 074).
 *
 * This is the framework's reference writable model, and it is one because a mailing list is the
 * case where importing genuinely belongs: moving a list off another provider is a real thing people
 * do, and the rows are records the site owner authored rather than history somebody else made.
 * Every other managed table CoreX ships is an audit or system log — activity, jobs, login attempts,
 * notifications, reading events, access grants — and those stay read-only, because manufacturing a
 * past event through a CSV would make the audit trail worthless.
 *
 * `status` is deliberately *not* writable. Confirmation state is owned by the double opt-in flow;
 * letting an import set it would let somebody import a list as pre-confirmed and skip consent
 * entirely. Imported subscribers arrive in the same pending state as anyone who typed their address
 * into the form.
 */
final class SubscriberTable
{
    public const NAME = 'subscribers';

    public function schema(): Table
    {
        return (new Table(self::NAME))
            ->id()->string('email')->string('status', 20)->text('topics')->boolean('consent')->timestamps();
    }

    public function managed(): ManagedTable
    {
        return new ManagedTable(
            self::NAME,
            'Newsletter subscribers',
            [
                ['id' => 'email', 'label' => 'Email'],
                ['id' => 'status', 'label' => 'Status'],
                ['id' => 'topics', 'label' => 'Topics'],
                ['id' => 'consent', 'label' => 'Consent'],
                ['id' => 'created_at', 'label' => 'Subscribed'],
            ],
            'corex',
            [
                [
                    'id'       => 'email',
                    'type'     => 'email',
                    'required' => true,
                    // The headers other list providers actually export.
                    'aliases'    => ['e-mail', 'email address', 'email_address', 'subscriber email'],
                    'validation' => ['max_length' => 190],
                ],
                [
                    'id'      => 'topics',
                    'type'    => 'json',
                    'aliases' => ['interests', 'tags', 'segments'],
                ],
                [
                    'id'      => 'consent',
                    'type'    => 'boolean',
                    'aliases' => ['opted_in', 'marketing consent', 'gdpr consent'],
                ],
            ],
            // A column nobody declared is more likely a mis-picked export than a value worth
            // guessing at, and a mailing list is not the place to guess.
            ManagedTable::UNKNOWN_REJECT,
            [
                [
                    'key'         => 'subscribers.index_lookups',
                    'version'     => '2026.07.1',
                    'description' => 'Index the columns every subscriber lookup filters on, so confirm, '
                        . 'unsubscribe, and publish notifications stop scanning the whole list.',
                    'plan' => [
                        'ALTER TABLE {table} ADD INDEX corex_subscribers_email (email(190))',
                        'ALTER TABLE {table} ADD INDEX corex_subscribers_status (status)',
                    ],
                    'transactional' => false,
                    // Rollback restores the table from its snapshot, which puts the schema back as
                    // well as the rows — so this one can honestly offer an undo.
                    'rollback_supported' => true,
                ],
            ],
            // An imported subscriber starts exactly where a subscriber who used the form starts:
            // pending, awaiting the confirmation email. Without this the row lands with no status
            // at all — a state neither the confirm flow nor the publish notifier recognises, so
            // the address could never be confirmed and would never receive anything.
            ['status' => 'pending'],
        );
    }
}
