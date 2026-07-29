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
 * The screens somebody actually opens in a normal week (spec 094).
 *
 * Grouped in one file because they are one editorial pass with one audience — the person handed a
 * finished site — rather than because they are one subject. The specialist screens (Data Models,
 * Access, Operations & Security, Blog Pro, Insights, Notifications) are deliberately not here; see
 * the spec for why they are a second pass rather than a thinner version of this one.
 *
 * Each guide names the ability its screen enforces, so a contributor is never shown instructions for
 * a screen that will refuse them.
 */
final class EverydayScreenGuides
{
    /** @return list<Guide> */
    public static function all(): array
    {
        return [
            self::overview(),
            self::addons(),
            self::formsAndFlows(),
            self::setup(),
        ];
    }

    private static function overview(): Guide
    {
        return Guide::for('corex-overview', __('Reading the Overview screen', 'corex'))
            ->withSummary(__('The one screen that tells you whether anything needs your attention, and what to do about it.', 'corex'))
            ->inSection('general')
            ->onScreen('admin.php?page=corex-settings')
            ->requiring('manage_options')
            ->ordered(10)
            ->withTopic(GuideTopic::for(
                'read-it',
                __('What it is telling you', 'corex'),
                __('Everything here is measured when you open the page, not remembered from a checklist.', 'corex'),
                [
                    new GuideStep(
                        __('In the CoreX menu, choose Overview.', 'corex'),
                        __('The screen opens with the site\'s current state: what is set up, what is not, and anything that needs attention.', 'corex'),
                        screenshot: new GuideScreenshot('overview', __('The Overview screen reporting the current state of the site.', 'corex')),
                    ),
                    new GuideStep(
                        __('Read anything marked as needing attention first.', 'corex'),
                        __('These are real checks against this install. A quiet screen means the site genuinely is in good shape, not that nothing was looked at.', 'corex'),
                    ),
                    new GuideStep(
                        __('Follow the link on any item you do not understand.', 'corex'),
                        __('Each one takes you to the screen where it can be fixed, rather than leaving you to work out where that is.', 'corex'),
                    ),
                ],
            ));
    }

    private static function addons(): Guide
    {
        return Guide::for('corex-addons', __('Turning features on and off', 'corex'))
            ->withSummary(__('Add-ons are the optional parts of CoreX. Anything switched off simply is not there — nothing breaks.', 'corex'))
            ->inSection('general')
            ->onScreen('admin.php?page=corex-addons')
            ->requiring('manage_options')
            ->ordered(30)
            ->withTopic(GuideTopic::for(
                'browse',
                __('See what is available and what is on', 'corex'),
                __('The tabs across the top filter the list.', 'corex'),
                [
                    new GuideStep(
                        __('In the CoreX menu, choose Add-ons.', 'corex'),
                        __('Every add-on is listed with its real state: Active, Inactive, Not installed, or a reason it cannot run.', 'corex'),
                        screenshot: new GuideScreenshot('addons', __('The Add-ons screen listing each add-on with its real status.', 'corex')),
                    ),
                    new GuideStep(
                        __('Read the status on any card that is not simply Active or Inactive.', 'corex'),
                        __('"Dependency missing" and "WooCommerce missing" mean the add-on needs something else first. Turning it on would not help until that is resolved.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'toggle',
                __('Turn one on', 'corex'),
                '',
                [
                    new GuideStep(
                        __('Find the add-on and use its activate control.', 'corex'),
                        __('It becomes Active, and whatever it adds — a screen, a block, a setting section — appears where it belongs.', 'corex'),
                        warning: __('Turning an add-on off does not delete anything it created. Content and settings stay where they are and come back if you switch it on again.', 'corex'),
                    ),
                    new GuideStep(
                        __('Follow the Documentation link on a card you are unsure about.', 'corex'),
                        __('It opens the reference page for that add-on rather than a general index.', 'corex'),
                    ),
                ],
            ));
    }

    private static function formsAndFlows(): Guide
    {
        return Guide::for('corex-forms-flows', __('Building and changing a form', 'corex'))
            ->withSummary(__('Forms are built in eight numbered stages. You can stop after any of them and come back — nothing is live until you publish.', 'corex'))
            ->inSection('communication')
            ->onScreen('admin.php?page=corex-forms')
            ->requiring('corex_manage_forms')
            ->ordered(5)
            ->withTopic(GuideTopic::for(
                'the-stages',
                __('The eight stages, and what each is for', 'corex'),
                __('The rail down the side shows Ready or Incomplete for each, so you can always see what is left.', 'corex'),
                [
                    new GuideStep(
                        __('1 Form — the questions you are asking.', 'corex'),
                        __('Add, remove and reorder fields, and set which are required.', 'corex'),
                    ),
                    new GuideStep(
                        __('2 Validation — what counts as a good answer.', 'corex'),
                        __('For example that an email field must contain a real address. Somebody filling the form is told before they submit, not after.', 'corex'),
                    ),
                    new GuideStep(
                        __('3 Routing — who this particular submission belongs to.', 'corex'),
                        __('You can send different answers to different people based on what was filled in.', 'corex'),
                    ),
                    new GuideStep(
                        __('4 Emails — what is sent when somebody submits.', 'corex'),
                        __('Both the notification to you and any acknowledgement to them.', 'corex'),
                    ),
                    new GuideStep(
                        __('5 Protection — spam handling for this form.', 'corex'),
                        __('Uses whatever captcha driver is set in Settings, and can be turned off for one form.', 'corex'),
                    ),
                    new GuideStep(
                        __('6 Success — what the visitor sees after submitting.', 'corex'),
                        __('A message in place of the form, or a redirect to another page.', 'corex'),
                    ),
                    new GuideStep(
                        __('7 Preview — the form as a visitor will meet it.', 'corex'),
                        __('Check the wording and the order here rather than on the live site.', 'corex'),
                    ),
                    new GuideStep(
                        __('8 Test — send one through yourself.', 'corex'),
                        __('It runs the whole path, including the email, and is marked as a test so it does not clutter the inbox.', 'corex'),
                        warning: __('Do this before publishing. A form that looks right and silently loses messages is indistinguishable from one nobody has used.', 'corex'),
                    ),
                ],
            ))
            ->withTopic(GuideTopic::for(
                'publish',
                __('Publish it, and change it later', 'corex'),
                __('Forms are versioned, so editing a live form does not change it under your visitors mid-submission.', 'corex'),
                [
                    new GuideStep(
                        __('Publish once the stages you need show Ready.', 'corex'),
                        __('The form becomes usable on the page it is placed on.', 'corex'),
                    ),
                    new GuideStep(
                        __('To change a published form, edit it and publish again.', 'corex'),
                        __('The previous version stays attached to the submissions it collected, so old messages still make sense against the questions that were actually asked.', 'corex'),
                    ),
                ],
            ));
    }

    private static function setup(): Guide
    {
        return Guide::for('corex-setup', __('Setting up a new site', 'corex'))
            ->withSummary(__('The wizard walks a fresh site from nothing to a working set of pages, and tells you what is left before launch.', 'corex'))
            ->inSection('general')
            ->onScreen('admin.php?page=corex-setup')
            ->requiring('corex_manage_setup')
            ->ordered(5)
            ->withTopic(GuideTopic::for(
                'run-it',
                __('Work through the wizard', 'corex'),
                __('Nine steps. You can leave and come back — it remembers where you were.', 'corex'),
                [
                    new GuideStep(
                        __('In the CoreX menu, choose Setup Wizard.', 'corex'),
                        __('It opens on Welcome, with the remaining steps listed and your progress marked.', 'corex'),
                        screenshot: new GuideScreenshot('setup-wizard', __('The Setup Wizard on its first step.', 'corex')),
                    ),
                    new GuideStep(
                        __('Fill in the Brand step.', 'corex'),
                        __('The same company details as Settings → Brand. Doing it here saves doing it there.', 'corex'),
                    ),
                    new GuideStep(
                        __('Choose a kit, and decide about demo content.', 'corex'),
                        __('A kit creates a set of pages laid out for a kind of site. Demo content fills them with placeholder text you replace.', 'corex'),
                    ),
                    new GuideStep(
                        __('Read the Review plan step before applying.', 'corex'),
                        __('It lists exactly what will be created and what will be left alone, so nothing happens that you have not seen.', 'corex'),
                        warning: __('Take a backup at the Backup step. Applying a kit creates pages and activates add-ons — undoing that by hand is slow.', 'corex'),
                    ),
                    new GuideStep(
                        __('Apply, then work through the Launch checklist.', 'corex'),
                        __('It reports eight real checks — search-engine visibility, debug output, email, spam protection, legal pages, whether forms have been tested, and performance. Blockers must be cleared; warnings are your judgement.', 'corex'),
                    ),
                ],
            ));
    }
}
