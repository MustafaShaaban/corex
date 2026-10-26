<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Activity;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Config\Access\ScreenNames;
use Corex\Config\Operations\OperationsMode;

/**
 * What happened, in a sentence a site owner reads (spec 118, US-OVR-07). The one source of activity
 * wording: the Overview's recent activity, the Insights security card and the Access audit log all
 * ask it, so the same event reads the same everywhere.
 *
 * The person is named by their display name today, read from WordPress, not the label the record
 * kept ("User #7"); somebody who no longer exists is "A deleted user". A kind with no sentence yet
 * reads "Something changed in <area>", never its key. Where the object of the event can still be
 * opened, the sentence carries a link to it.
 */
final class ActivitySentences
{
    public function __construct(private readonly OperationsMode $modes)
    {
    }

    /**
     * @return array{text:string,href:string}
     */
    public function of(ActivityEvent $event): array
    {
        $who   = $this->who($event);
        $count = max(1, (int) ($event->context['count'] ?? 1));

        return match ($event->kind) {
            'submission.trashed' => $this->said(
                sprintf(
                    /* translators: 1: who, 2: how many submissions. */
                    _n('%1$s moved %2$s submission to the trash', '%1$s moved %2$s submissions to the trash', $count, 'corex'),
                    $who,
                    number_format_i18n($count),
                ),
                'admin.php?page=corex-submissions&view=trash',
            ),
            'submission.restored' => $this->said(
                sprintf(
                    /* translators: 1: who, 2: how many submissions. */
                    _n('%1$s restored %2$s submission from the trash', '%1$s restored %2$s submissions from the trash', $count, 'corex'),
                    $who,
                    number_format_i18n($count),
                ),
                'admin.php?page=corex-submissions',
            ),
            'submission.deleted' => $this->said(sprintf(
                /* translators: 1: who, 2: how many submissions. */
                _n('%1$s deleted %2$s submission for good', '%1$s deleted %2$s submissions for good', $count, 'corex'),
                $who,
                number_format_i18n($count),
            )),
            'submission.export.queued' => $this->said(
                sprintf(
                    /* translators: 1: who, 2: how many submissions. */
                    _n('%1$s exported %2$s submission', '%1$s exported %2$s submissions', $count, 'corex'),
                    $who,
                    number_format_i18n($count),
                ),
                'admin.php?page=corex-submissions',
            ),
            'submission.export.deleted', 'data.export.deleted' => $this->said(sprintf(
                /* translators: %s: who. */
                __('%s deleted an export', 'corex'),
                $who,
            )),
            SecurityActivityRecorder::MODE_CHANGED => $this->said(
                sprintf(
                    /* translators: 1: who changed the mode. 2: the mode the site moved to, e.g. Production. */
                    __('%1$s changed the mode to %2$s', 'corex'),
                    $who,
                    $this->modes->describe((string) ($event->context['to'] ?? $event->targetId))['label'],
                ),
                'admin.php?page=corex-operations-security&tab=environment',
            ),
            SecurityActivityRecorder::ACCESS_DENIED => $this->said(
                ($event->context['refusal'] ?? '') === 'data'
                    ? $this->refusedRecords($who, $event->targetId)
                    : $this->refused($who, $event->targetId),
                'admin.php?page=corex-access&tab=audit',
            ),
            SecurityActivityRecorder::LOGIN_LOCKED => $this->said(
                __('A sign-in was locked out after too many failed attempts', 'corex'),
                'admin.php?page=corex-operations-security&tab=login',
            ),
            'access.request.created' => $this->access(sprintf(/* translators: %s: who asked. */ __('%s asked for access', 'corex'), $who)),
            'access.request.approved' => $this->access(sprintf(/* translators: %s: who approved. */ __('%s approved an access request', 'corex'), $who)),
            'access.request.denied' => $this->access(sprintf(/* translators: %s: who denied. */ __('%s denied an access request', 'corex'), $who)),
            'access.request.cancelled' => $this->access(sprintf(/* translators: %s: who cancelled. */ __('%s cancelled an access request', 'corex'), $who)),
            'access.role.changed' => $this->access(sprintf(/* translators: %s: who changed them. */ __('%s changed the abilities of a role', 'corex'), $who)),
            'access.grant.revoked' => $this->access(sprintf(/* translators: %s: who revoked it. */ __('%s revoked an access grant', 'corex'), $who)),
            'access.grant.expired' => $this->access(__('An access grant ended', 'corex')),
            'addon.enabled', 'addon.disabled' => $this->said($this->addon($event, $who), 'admin.php?page=corex-addons'),
            'data.record.created', 'data.record.updated', 'data.record.deleted' => $this->said(
                $this->records($event, $who),
                'admin.php?page=corex-data-models&tab=records&source=' . rawurlencode($event->targetId),
            ),
            'data.import.completed' => $this->said(sprintf(/* translators: %s: who. */ __('%s imported records', 'corex'), $who)),
            'data.export.completed' => $this->said(sprintf(/* translators: %s: who. */ __('%s exported records', 'corex'), $who)),
            default => $this->said(sprintf(
                /* translators: %s: the part of CoreX where something changed, e.g. "Submissions". */
                __('Something changed in %s', 'corex'),
                $this->area($event->area),
            )),
        };
    }

    /** A refusal of a CoreX screen, as the Access audit log and the activity log both say it. */
    public function refused(string $who, string $screen): string
    {
        return sprintf(
            /* translators: 1: who was refused. 2: the CoreX screen, e.g. Settings. */
            __('%1$s was refused access to %2$s', 'corex'),
            $who,
            ScreenNames::of($screen),
        );
    }

    /** A refusal of one data model's records on the Data screen. */
    public function refusedRecords(string $who, string $model): string
    {
        return sprintf(
            /* translators: 1: who was refused. 2: the key of a data model, e.g. "table-access-requests". */
            __('%1$s was refused the records of %2$s on the Data screen', 'corex'),
            $who,
            $model,
        );
    }

    /** A person by their display name now, or that they are gone. */
    public function person(int $userId): string
    {
        $user = $userId > 0 ? get_userdata($userId) : false;

        $name = is_object($user) ? (string) ($user->display_name ?? '') : '';

        return $name !== ''
            ? $name
            : __('A deleted user', 'corex');
    }

    private function who(ActivityEvent $event): string
    {
        if ($event->actorKind === ActivityEvent::ACTOR_USER) {
            return $this->person($event->actorId);
        }

        return match ($event->actorKind) {
            ActivityEvent::ACTOR_CLI  => __('The command line', 'corex'),
            ActivityEvent::ACTOR_CRON => __('A scheduled task', 'corex'),
            default                   => __('CoreX', 'corex'),
        };
    }

    private function addon(ActivityEvent $event, string $who): string
    {
        $failed = $event->outcome === ActivityEvent::OUTCOME_FAILURE;

        if ($event->kind === 'addon.enabled') {
            return $failed
                /* translators: 1: who, 2: an add-on's name. */
                ? sprintf(__('%1$s tried to turn on %2$s, and it did not turn on', 'corex'), $who, $event->targetLabel)
                /* translators: 1: who, 2: an add-on's name. */
                : sprintf(__('%1$s turned on %2$s', 'corex'), $who, $event->targetLabel);
        }

        return $failed
            /* translators: 1: who, 2: an add-on's name. */
            ? sprintf(__('%1$s tried to turn off %2$s, and it did not turn off', 'corex'), $who, $event->targetLabel)
            /* translators: 1: who, 2: an add-on's name. */
            : sprintf(__('%1$s turned off %2$s', 'corex'), $who, $event->targetLabel);
    }

    private function records(ActivityEvent $event, string $who): string
    {
        $count = max(1, (int) ($event->context['affected_count'] ?? 1));

        return match ($event->kind) {
            'data.record.created' => sprintf(
                /* translators: 1: who, 2: how many records. */
                _n('%1$s added %2$s record', '%1$s added %2$s records', $count, 'corex'),
                $who,
                number_format_i18n($count),
            ),
            'data.record.deleted' => sprintf(
                /* translators: 1: who, 2: how many records. */
                _n('%1$s deleted %2$s record', '%1$s deleted %2$s records', $count, 'corex'),
                $who,
                number_format_i18n($count),
            ),
            default => sprintf(
                /* translators: 1: who, 2: how many records. */
                _n('%1$s changed %2$s record', '%1$s changed %2$s records', $count, 'corex'),
                $who,
                number_format_i18n($count),
            ),
        };
    }

    /** @return array{text:string,href:string} */
    private function access(string $text): array
    {
        return $this->said($text, 'admin.php?page=corex-access');
    }

    /** @return array{text:string,href:string} */
    private function said(string $text, string $path = ''): array
    {
        return ['text' => $text, 'href' => $path === '' ? '' : admin_url($path)];
    }

    /** The name a person reads for an area of CoreX. */
    private function area(string $area): string
    {
        return match ($area) {
            ActivityEvent::AREA_ADDONS        => __('Add-ons', 'corex'),
            ActivityEvent::AREA_FORMS         => __('Forms & Flows', 'corex'),
            ActivityEvent::AREA_SUBMISSIONS   => __('Submissions', 'corex'),
            ActivityEvent::AREA_DATA, ActivityEvent::AREA_DATA_MODELS => __('Data', 'corex'),
            ActivityEvent::AREA_EMAIL         => __('Email Studio', 'corex'),
            ActivityEvent::AREA_BLOG          => __('Blog Pro', 'corex'),
            ActivityEvent::AREA_INSIGHTS      => __('Insights', 'corex'),
            ActivityEvent::AREA_SETUP         => __('Setup', 'corex'),
            ActivityEvent::AREA_SETTINGS      => __('Settings', 'corex'),
            ActivityEvent::AREA_OPERATIONS    => __('Operations', 'corex'),
            ActivityEvent::AREA_SECURITY      => __('Security', 'corex'),
            ActivityEvent::AREA_NOTIFICATIONS => __('Notifications', 'corex'),
            ActivityEvent::AREA_ACCESS        => __('Access & Abilities', 'corex'),
            ActivityEvent::AREA_THEME         => __('Theme', 'corex'),
            ActivityEvent::AREA_DOCS          => __('Guides', 'corex'),
            default                           => __('CoreX', 'corex'),
        };
    }
}
