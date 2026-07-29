<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Corex\Guides;

defined('ABSPATH') || exit;

use Corex\Guides\Guide;
use Corex\Guides\GuideStep;
use Corex\Guides\GuideTopic;

/**
 * The guide for somebody who has never seen this before (spec 094).
 *
 * Every other guide answers "how do I do X". This one answers the question underneath them, which
 * nothing in the admin answered at all: **what is this, and where do I look.** Somebody handed a
 * finished site meets a menu of thirteen unfamiliar words, and until now the guides assumed they
 * already knew which one they wanted.
 *
 * Gated on `read` rather than a CoreX ability on purpose — the reader who needs it most is the one
 * with the fewest permissions, and refusing them an orientation because they cannot manage settings
 * would be refusing help to exactly the person asking for it.
 */
final class OrientationGuide
{
    public static function guide(): Guide
    {
        return Guide::for('corex-orientation', __('Getting your bearings', 'corex'))
            ->withSummary(__('What CoreX is, what each thing in the menu does, and where to start if somebody has just handed you this site.', 'corex'))
            ->inSection('general')
            ->onScreen('admin.php?page=corex-settings')
            ->requiring('read')
            ->ordered(1)
            ->withTopic(GuideTopic::for(
                'what-is-this',
                __('What CoreX is, in one minute', 'corex'),
                __('You do not need to know how it was built to run it.', 'corex'),
                [
                    new GuideStep(
                        __('Look at the sidebar for a menu called CoreX Framework.', 'corex'),
                        __('That is everything this framework adds. WordPress\'s own menus — Posts, Media, Pages, Users — are unchanged and work exactly as they always did.', 'corex'),
                    ),
                    new GuideStep(
                        __('Think of CoreX as the machinery behind your site rather than the site itself.', 'corex'),
                        __('Your pages and posts live where they always have. CoreX handles the parts around them: the forms people fill in, the messages those forms produce, the email that goes out, and who on your team may touch what.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'the-menu',
                __('What each menu item is for', 'corex'),
                __('You will not need all of these. Most people use three or four.', 'corex'),
                [
                    new GuideStep(
                        __('Overview — open this first.', 'corex'),
                        __('One screen showing whether the site is healthy, what is still unfinished, and what needs attention. If something is wrong, it says so here.', 'corex'),
                    ),
                    new GuideStep(
                        __('Submissions — where messages arrive.', 'corex'),
                        __('Everything anybody sends through a form on your site lands here. This is the screen most people open most often.', 'corex'),
                    ),
                    new GuideStep(
                        __('Forms & Flows — where the forms themselves are built.', 'corex'),
                        __('You only come here to change what a form asks or where its answers go, not to read the answers.', 'corex'),
                    ),
                    new GuideStep(
                        __('Email Studio — what your site sends, and whether it arrived.', 'corex'),
                        __('Templates for automatic emails, and a log showing what was sent and what failed.', 'corex'),
                    ),
                    new GuideStep(
                        __('Settings — the site\'s own details.', 'corex'),
                        __('Company name, contact details, logo, and the switches for each feature. Every field is explained in the Settings guide on this page.', 'corex'),
                    ),
                    new GuideStep(
                        __('Add-ons — the optional pieces.', 'corex'),
                        __('Turn features on and off. Anything switched off simply is not there; nothing breaks.', 'corex'),
                    ),
                    new GuideStep(
                        __('The rest — Access, Operations & Security, Data, Notifications, Blog Pro, Insights.', 'corex'),
                        __('Specialist screens. You will be sent to one when you need it; you do not need to explore them first.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'first-week',
                __('What to do in your first week', 'corex'),
                __('In order, and none of it takes long.', 'corex'),
                [
                    new GuideStep(
                        __('Open Overview and read anything it flags.', 'corex'),
                        __('It reports real checks, not a checklist somebody wrote once — so if it is quiet, the site genuinely is fine.', 'corex'),
                    ),
                    new GuideStep(
                        __('Open Settings and fill in your company details.', 'corex'),
                        __('The name, contact address and logo appear across the site and on the emails it sends, so this is the change with the widest effect for the least work.', 'corex'),
                    ),
                    new GuideStep(
                        __('Send yourself a test message through your own contact form.', 'corex'),
                        __('It appears in Submissions, and Email Studio shows whether the notification email actually left. This is the fastest way to know the whole path works.', 'corex'),
                        warning: __('Do this before you tell anybody the site is live. A contact form that silently drops messages looks identical to one nobody has used.', 'corex'),
                    ),
                    new GuideStep(
                        __('Decide who else needs access, and give them only what they need.', 'corex'),
                        __('Access & Abilities lets you grant one area at a time rather than making somebody an administrator.', 'corex'),
                    ),
                ],
            ));
    }
}
