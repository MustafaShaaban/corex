<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Corex\Guides;

defined('ABSPATH') || exit;

use Corex\Guides\Guide;
use Corex\Guides\GuideScreenshot;
use Corex\Guides\GuideStep;
use Corex\Guides\GuideTopic;

/**
 * The screens somebody is *sent* to (spec 096).
 *
 * Spec 094 documented the everyday path and named this seam rather than shipping fourteen thin
 * guides. These six are the other half: Data Models, Access & Abilities, Operations & Security,
 * Notifications, Blog Pro and Insights.
 *
 * They read differently on purpose. Nobody browses Operations & Security out of curiosity — they
 * arrive because something told them to, usually while worried. So each of these opens with *why
 * you are here* rather than with a tour, and says plainly which actions are reversible.
 *
 * Every guide names the ability its screen enforces, so a contributor is never given instructions
 * for a screen that will refuse them.
 */
final class SpecialistScreenGuides
{
    /** @return list<Guide> */
    public static function all(): array
    {
        return [
            self::access(),
            self::operations(),
            self::dataModels(),
            self::notifications(),
            self::blogPro(),
            self::insights(),
        ];
    }

    private static function access(): Guide
    {
        return Guide::for('corex-access', __('Giving somebody access', 'corex'))
            ->withSummary(__('How to let a colleague into one part of CoreX without making them an administrator.', 'corex'))
            ->inSection('operations')
            ->onScreen('admin.php?page=corex-access')
            ->requiring('corex_manage_access')
            ->ordered(10)
            ->withTopic(GuideTopic::for(
                'grant',
                __('Let somebody into one area', 'corex'),
                __('CoreX abilities are separate from WordPress roles, so you can grant one without changing what somebody can do to your posts.', 'corex'),
                [
                    new GuideStep(
                        __('In the CoreX menu, choose Access & Abilities, then the Role matrix tab.', 'corex'),
                        __('A grid of every CoreX ability against every role on the site.', 'corex'),
                        screenshot: new GuideScreenshot('access', __('Access and Abilities, showing the role matrix.', 'corex')),
                    ),
                    new GuideStep(
                        __('Find the ability by what it lets somebody do, not by its name.', 'corex'),
                        __('Each row says plainly what it grants — for example "Read and process form submissions" — and carries a risk label of normal, sensitive, dangerous or critical.', 'corex'),
                    ),
                    new GuideStep(
                        __('Tick it for the role that needs it, and save.', 'corex'),
                        __('Everybody in that role gains it immediately. There is nothing to log out and back in for.', 'corex'),
                        warning: __('Anything marked critical — managing access itself, or running dangerous actions — lets the holder change what everybody else can do. Grant those to people you would make an administrator anyway.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'requests',
                __('Answer somebody who asked for access', 'corex'),
                __('When a person is refused a screen, they can ask for it from the refusal page itself.', 'corex'),
                [
                    new GuideStep(
                        __('Open the Overview tab and look for pending requests.', 'corex'),
                        __('Each shows who asked, what they asked for, and the reason they gave.', 'corex'),
                    ),
                    new GuideStep(
                        __('Approve or decline it.', 'corex'),
                        __('Approving grants the ability to that person. Declining records the decision; it does not tell them off.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'audit',
                __('See what was refused', 'corex'),
                '',
                [
                    new GuideStep(
                        __('Open the Audit log tab.', 'corex'),
                        __('Every refused attempt, with who and what. Useful when somebody says "it will not let me in" and cannot say where.', 'corex'),
                    ),
                    new GuideStep(
                        __('Use the Access denied tab to see what a refused person actually sees.', 'corex'),
                        __('It is a preview, so looking at it records nothing in the audit log.', 'corex'),
                    ),
                ],
            ));
    }

    private static function operations(): Guide
    {
        return Guide::for('corex-operations', __('Operations and security', 'corex'))
            ->withSummary(__('Switching the site between development and live, keeping logins safe, and clearing caches.', 'corex'))
            ->inSection('operations')
            ->onScreen('admin.php?page=corex-operations-security')
            ->requiring('corex_manage_operations')
            ->ordered(20)
            ->withTopic(GuideTopic::for(
                'mode',
                __('Change the operating mode', 'corex'),
                __('Development, Staging or Production. This is the most consequential switch in CoreX.', 'corex'),
                [
                    new GuideStep(
                        __('Open the Environment & Maintenance tab.', 'corex'),
                        __('The current mode is shown with the evidence behind it, not just a label.', 'corex'),
                        screenshot: new GuideScreenshot('operations-security', __('Operations and Security, showing the operating mode.', 'corex')),
                    ),
                    new GuideStep(
                        __('Choose the mode you want and read what it says before confirming.', 'corex'),
                        __('It lists exactly what changing to that mode will do, and asks you to type a confirmation. Nothing happens until you do.', 'corex'),
                        warning: __('Moving to Production turns on protections and turns off debugging output. Moving away from it does the reverse — do not leave a public site in Development, where errors are shown to visitors.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'logins',
                __('Protect the login page', 'corex'),
                '',
                [
                    new GuideStep(
                        __('Open the Login Protection tab.', 'corex'),
                        __('It shows the lockout policy, anybody currently locked out, and the login address.', 'corex'),
                    ),
                    new GuideStep(
                        __('If a colleague is locked out, clear their lockout here.', 'corex'),
                        __('Faster than waiting it out, and it tells you the attempt came from an address you recognise.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'cache',
                __('Clear a cache', 'corex'),
                __('Use this when a change you made is not showing up.', 'corex'),
                [
                    new GuideStep(
                        __('Open the Cache & Performance tab.', 'corex'),
                        __('Every caching layer is listed with what it holds, whether it is on, and when it was last checked.', 'corex'),
                    ),
                    new GuideStep(
                        __('Use the Clear button on the layer you mean.', 'corex'),
                        __('A layer with no button says why instead — some caches are not ours to clear, and it names which.', 'corex'),
                        warning: __('Clear the specific layer rather than everything. Nothing here deletes by pattern, deliberately: a blanket sweep of CoreX data would reset brute-force protection at exactly the moment somebody reaches for it.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'hardening',
                __('Check the site is hardened', 'corex'),
                '',
                [
                    new GuideStep(
                        __('Open the Hardening tab and read any warnings.', 'corex'),
                        __('These are real checks against this install, each with what to do about it. A quiet tab means the checks passed, not that they were skipped.', 'corex'),
                    ),
                ],
            ));
    }

    private static function dataModels(): Guide
    {
        return Guide::for('corex-data-models', __('Finding, importing and exporting data', 'corex'))
            ->withSummary(__('Search stored records, bring data in from a spreadsheet, and take it out again.', 'corex'))
            ->inSection('operations')
            ->onScreen('admin.php?page=corex-data-models')
            ->requiring('corex_manage_data')
            ->ordered(30)
            ->withTopic(GuideTopic::for(
                'records',
                __('Find a record', 'corex'),
                __('The Records tab is a search across everything CoreX stores.', 'corex'),
                [
                    new GuideStep(
                        __('Open Data, then the Records tab, and pick a source on the left.', 'corex'),
                        __('The table fills with that source, and the address bar updates so you can bookmark or share the view.', 'corex'),
                        screenshot: new GuideScreenshot('data-models', __('The Data screen with its source list and records table.', 'corex')),
                    ),
                    new GuideStep(
                        __('Narrow it with the query bar, then open a row.', 'corex'),
                        __('The detail panel shows every stored field, including ones the list does not have room for.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'import',
                __('Import from a spreadsheet', 'corex'),
                __('Four steps, and it will not write anything until the third.', 'corex'),
                [
                    new GuideStep(
                        __('Open the Import tab and choose your CSV.', 'corex'),
                        __('It reads the file and shows you the columns it found.', 'corex'),
                    ),
                    new GuideStep(
                        __('Check the plan it shows you.', 'corex'),
                        __('Which column becomes which field, how many rows will be created, and how many will be skipped.', 'corex'),
                    ),
                    new GuideStep(
                        __('Run it, then read the report.', 'corex'),
                        __('It names what was created and what was rejected, with the reason for each rejection.', 'corex'),
                        warning: __('Take a backup first. An import that maps the wrong column is quick to run and slow to undo by hand.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'export',
                __('Export data out', 'corex'),
                '',
                [
                    new GuideStep(
                        __('Open the Export tab and choose what you want.', 'corex'),
                        __('The export is queued rather than made immediately, so a large one does not time out.', 'corex'),
                    ),
                    new GuideStep(
                        __('Wait for the notification, then download it.', 'corex'),
                        __('CoreX tells you when the file is ready and links straight to it.', 'corex'),
                        warning: __('An export of personal data is personal data. It is recorded in the audit log, and your privacy policy should account for where the file goes next.', 'corex'),
                    ),
                ],
            ));
    }

    private static function notifications(): Guide
    {
        return Guide::for('corex-notifications', __('Keeping on top of notifications', 'corex'))
            ->withSummary(__('What the bell is telling you, and the difference between having read something and having dealt with it.', 'corex'))
            ->inSection('operations')
            ->onScreen('admin.php?page=corex-notifications')
            ->requiring('corex_manage_notifications')
            ->ordered(40)
            ->withTopic(GuideTopic::for(
                'read',
                __('Read and act on one', 'corex'),
                __('The bell in the CoreX header opens a quick list; the full screen has everything.', 'corex'),
                [
                    new GuideStep(
                        __('Select the bell, or open Notifications from the menu.', 'corex'),
                        __('Each notification says what happened, how serious it is, where it came from, and how many times it has occurred.', 'corex'),
                        screenshot: new GuideScreenshot('notifications', __('The Notifications screen.', 'corex')),
                    ),
                    new GuideStep(
                        __('Follow the title or the button to go where it is about.', 'corex'),
                        __('A new submission opens the inbox filtered to that form; a failed job opens Operations. A notification with no link is one you cannot act on from here.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'states',
                __('Read is not the same as resolved', 'corex'),
                __('This distinction is the whole point of the screen.', 'corex'),
                [
                    new GuideStep(
                        __('Use Mark read when you have seen it.', 'corex'),
                        __('That is a fact about you. The condition it describes may still be true, and it stays under Action needed if it is.', 'corex'),
                    ),
                    new GuideStep(
                        __('Use Mark resolved when the underlying problem is fixed.', 'corex'),
                        __('That is a fact about the site, and it clears the notification for everybody.', 'corex'),
                        warning: __('Resolving something that is not actually fixed hides it from the whole team. Snooze it instead if you just need it out of the way today.', 'corex'),
                    ),
                    new GuideStep(
                        __('Use the Preferences tab to choose what reaches you.', 'corex'),
                        __('Some categories cannot be silenced — security notices among them — and the screen says which.', 'corex'),
                    ),
                ],
            ));
    }

    private static function blogPro(): Guide
    {
        return Guide::for('corex-blog-pro', __('Running an editorial workflow', 'corex'))
            ->withSummary(__('Move posts through review, moderate the comments waiting on them, and see what people actually read.', 'corex'))
            ->inSection('content')
            ->onScreen('admin.php?page=corex-blog-pro')
            ->requiring('corex_manage_blog')
            ->ordered(20)
            ->withTopic(GuideTopic::for(
                'review',
                __('Move a post through review', 'corex'),
                '',
                [
                    new GuideStep(
                        __('Open Blog Pro and choose the post you are working on.', 'corex'),
                        __('The choice is in the address, so you can send somebody a link to the exact post.', 'corex'),
                        screenshot: new GuideScreenshot('blog-pro', __('Blog Pro, showing the editorial workspace.', 'corex')),
                    ),
                    new GuideStep(
                        __('Move it to the next stage, leaving a note.', 'corex'),
                        __('The note travels with the post, so the next person knows what you wanted changed.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'comments',
                __('Deal with the comments on it', 'corex'),
                '',
                [
                    new GuideStep(
                        __('Work through the comments waiting on that post.', 'corex'),
                        __('Approve, mark spam or trash each. They are the comments for the post you are already reading, not the whole site.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'analytics',
                __('See how it did', 'corex'),
                __('A thirty-day window over authors and reading.', 'corex'),
                [
                    new GuideStep(
                        __('Read the author and reading panels.', 'corex'),
                        __('"No data yet" and "zero" are shown differently on purpose — one means nothing has been measured, the other that it was measured and was nothing.', 'corex'),
                    ),
                ],
            ));
    }

    private static function insights(): Guide
    {
        return Guide::for('corex-insights', __('Checking performance and readiness', 'corex'))
            ->withSummary(__('Two checks you run on demand: is the site fast, and is it ready to be live.', 'corex'))
            ->inSection('operations')
            ->onScreen('admin.php?page=corex-insights')
            ->requiring('manage_options')
            ->ordered(50)
            ->withTopic(GuideTopic::for(
                'run',
                __('Run a check', 'corex'),
                __('Nothing runs on its own — these cost time and an external call, so you ask for them.', 'corex'),
                [
                    new GuideStep(
                        __('Open Insights and choose Run check on the card you want.', 'corex'),
                        __('The result replaces the card, with what was measured and when.', 'corex'),
                        screenshot: new GuideScreenshot('insights', __('The Insights screen with its two check cards.', 'corex')),
                    ),
                    new GuideStep(
                        __('If a check says it could not run, read why.', 'corex'),
                        __('Usually a missing API key, which is set under Settings → Insights. "Could not look" is reported differently from "looked and found a problem".', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'act',
                __('Act on what it found', 'corex'),
                '',
                [
                    new GuideStep(
                        __('Work down the readiness results before going live.', 'corex'),
                        __('Each names the thing to change and where. The same checks feed the Setup Wizard launch checklist, so clearing them here clears them there.', 'corex'),
                    ),
                ],
            ));
    }
}
