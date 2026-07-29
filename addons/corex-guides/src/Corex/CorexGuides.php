<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Corex;

defined('ABSPATH') || exit;

use Corex\Guides\Corex\Guides\EverydayScreenGuides;
use Corex\Guides\Corex\Guides\OrientationGuide;
use Corex\Guides\Corex\Guides\SettingsGuide;
use Corex\Guides\Guide;
use Corex\Guides\GuideScreenshot;
use Corex\Guides\GuideStep;
use Corex\Guides\GuideTopic;

/**
 * Corex's own guides, for the person handed a finished site (spec 084, from spec 082's content).
 *
 * Written for somebody who did not build this and does not want to learn a framework — the audience
 * spec 082 identified: they want to answer a message, publish a post, or find out why a page is
 * slow. Every step names the control it means and says what should happen, so a reader can tell
 * whether it worked without asking anybody.
 *
 * **Each guide names the capability it needs**, so a contributor who cannot open the Submissions
 * inbox is never shown instructions for it. Registered through the same public seam a site plugin
 * uses, so the seam is exercised by the framework and cannot rot unnoticed.
 *
 * Spec 094 widened this from four guides to ten. The guides added there live one file each under
 * `Guides/` rather than in this one — ten guides inline would be a file nobody reads to change one
 * step. This class is now the composition point and the four original guides; anything new goes in
 * its own file.
 *
 * @see \Corex\Guides\GuideRegistry
 */
final class CorexGuides
{
    /**
     * @return list<Guide>
     */
    public static function all(): array
    {
        return [
            // Orientation first, by `ordered(1)` as well as by position: it is the guide for
            // somebody who does not yet know which of the others they want.
            OrientationGuide::guide(),
            SettingsGuide::guide(),
            ...EverydayScreenGuides::all(),
            self::submissions(),
            self::publishing(),
            self::email(),
            self::performance(),
        ];
    }

    private static function submissions(): Guide
    {
        return Guide::for('corex-submissions', __('Reading and answering messages', 'corex'))
            ->withSummary(__('Everything sent through a form on your site arrives in one inbox, where you can read it, reply, and mark it dealt with.', 'corex'))
            ->inSection('communication')
            ->onScreen('admin.php?page=corex-submissions')
            ->requiring('corex_manage_submissions')
            ->ordered(10)
            ->withTopic(GuideTopic::for(
                'read',
                __('Read a message somebody sent you', 'corex'),
                __('New form submissions appear at the top of the list.', 'corex'),
                [
                    new GuideStep(
                        __('In the Corex menu, choose Submissions.', 'corex'),
                        __('The inbox opens, listing every message with the newest first.', 'corex'),
                        screenshot: new GuideScreenshot('submissions-inbox', __('The Submissions inbox listing recent messages.', 'corex')),
                    ),
                    new GuideStep(
                        __('Select a row.', 'corex'),
                        __('A panel opens beside the list with everything the sender filled in.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'reply',
                __('Reply to somebody', 'corex'),
                __('Replies are sent from your site and are styled like the rest of its email.', 'corex'),
                [
                    new GuideStep(
                        __('Open the message, then choose Reply.', 'corex'),
                        __('A box appears for your answer.', 'corex'),
                    ),
                    new GuideStep(
                        __('Write your reply and choose Send.', 'corex'),
                        __('The reply is sent and recorded against the message, so anybody else looking at it can see it was answered.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'export',
                __('Export messages to a spreadsheet', 'corex'),
                '',
                [
                    new GuideStep(
                        __('Filter the list down to what you want, then choose Export.', 'corex'),
                        __('A CSV file downloads, containing only the rows currently listed.', 'corex'),
                        warning: __('The file contains personal information people sent you. It is recorded in the audit log, and how you store it is your responsibility under your privacy policy.', 'corex'),
                    ),
                ],
            ));
    }

    private static function publishing(): Guide
    {
        return Guide::for('corex-publishing', __('Publishing a post', 'corex'))
            ->withSummary(__('Write a post, choose where it appears, and publish it — or schedule it for later.', 'corex'))
            ->inSection('content')
            ->onScreen('edit.php')
            ->requiring('publish_posts')
            ->ordered(10)
            ->withTopic(GuideTopic::for(
                'write',
                __('Write and publish', 'corex'),
                '',
                [
                    new GuideStep(
                        __('Choose Posts, then Add Post.', 'corex'),
                        __('The editor opens with an empty post.', 'corex'),
                    ),
                    new GuideStep(
                        __('Type a title, then write the body below it.', 'corex'),
                        __('Your work is saved as a draft automatically as you write.', 'corex'),
                    ),
                    new GuideStep(
                        __('In the sidebar, pick at least one category.', 'corex'),
                        __('The post will appear in that category’s listing once published.', 'corex'),
                    ),
                    new GuideStep(
                        __('Choose Publish, then confirm.', 'corex'),
                        __('The post becomes visible on your site immediately.', 'corex'),
                        warning: __('Publishing makes the post public straight away. If it is not ready, use Save draft instead, or set a date under Publish to schedule it.', 'corex'),
                    ),
                ],
            ));
    }

    private static function email(): Guide
    {
        return Guide::for('corex-email', __('Checking whether an email was sent', 'corex'))
            ->withSummary(__('When somebody says they did not get an email, this is where you find out what actually happened.', 'corex'))
            ->inSection('communication')
            ->onScreen('admin.php?page=corex-email-studio')
            ->requiring('corex_manage_email')
            ->ordered(20)
            ->withTopic(GuideTopic::for(
                'check',
                __('Find out what happened to an email', 'corex'),
                __('Your site records every message it tried to send.', 'corex'),
                [
                    new GuideStep(
                        __('In the Corex menu, choose Email Studio, then the Delivery tab.', 'corex'),
                        __('A list of recent messages appears, each with what happened to it.', 'corex'),
                        screenshot: new GuideScreenshot('email-delivery', __('The Email Studio delivery log.', 'corex')),
                    ),
                    new GuideStep(
                        __('Find the message by the recipient’s address.', 'corex'),
                        __('Sent means your site handed it over successfully. Failed means it did not, and the reason is shown beside it.', 'corex'),
                    ),
                    new GuideStep(
                        __('If it says Sent but the person still has not received it, ask them to check their spam folder.', 'corex'),
                        __('Your site cannot see what happens after a message leaves it — Sent is the last thing it can honestly report.', 'corex'),
                    ),
                ],
            ));
    }

    private static function performance(): Guide
    {
        return Guide::for('corex-performance', __('Finding out why a page is slow', 'corex'))
            ->withSummary(__('Your site measures its own speed. When something feels slow, this tells you whether it is and where the time goes.', 'corex'))
            ->inSection('operations')
            ->onScreen('admin.php?page=corex-insights')
            ->requiring('manage_options')
            ->ordered(10)
            ->withTopic(GuideTopic::for(
                'diagnose',
                __('Check whether a page is actually slow', 'corex'),
                '',
                [
                    new GuideStep(
                        __('In the Corex menu, choose Insights.', 'corex'),
                        __('Recent page timings appear, slowest first.', 'corex'),
                    ),
                    new GuideStep(
                        __('Look at the slowest entries and note what they have in common.', 'corex'),
                        __('One slow page usually points at that page’s content; every page being slow points at the site or the server.', 'corex'),
                    ),
                    new GuideStep(
                        __('If everything is slow, open Operations & Security and check the cache status.', 'corex'),
                        __('A cache that is off or empty explains site-wide slowness more often than anything else.', 'corex'),
                    ),
                ],
            ));
    }
}
