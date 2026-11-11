<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Environment\SiteEnvironment;
use Corex\Http\PublicSubmissionPause;

/**
 * What the operations mode tells a visitor at a flow (spec 124, US-VIS-04).
 *
 * On a development or staging site a note above the form says it is a test site, so nobody takes a
 * test submission for one that reached the team. While forms are paused the panel takes the form's
 * place, with the owner's words and the time they expect to be back. Each answer comes from where
 * the rest of CoreX gets it: the environment from {@see SiteEnvironment}, the pause from
 * {@see PublicSubmissionPause}, the same gate that refuses the submission itself.
 */
final readonly class VisitorFormNotice
{
    private const TEST_ENVIRONMENTS = [SiteEnvironment::DEVELOPMENT, SiteEnvironment::STAGING];

    public function __construct(
        private SiteEnvironment $environment,
        private PublicSubmissionPause $pause,
    ) {
    }

    /** The paused panel when forms are paused for this visitor, '' otherwise. */
    public function pausedPanel(): string
    {
        if ($this->pause->closedBy() !== PublicSubmissionPause::FORMS_PAUSED) {
            return '';
        }

        return sprintf(
            '<div class="corex-flow-notice corex-flow-notice--paused"><p class="corex-flow-notice__title">%1$s</p>'
            . '<p class="corex-flow-notice__message">%2$s</p></div>',
            esc_html__('Forms are paused', 'corex'),
            esc_html($this->pause->message(PublicSubmissionPause::FORMS_PAUSED)),
        );
    }

    /**
     * The test-site note on a development or staging site, '' otherwise. A site closed to visitors
     * (Coming soon, Forms paused, Maintenance) says what it says instead.
     */
    public function testSiteNote(): string
    {
        $mode = get_option(SiteEnvironment::MODE_OPTION, '');
        if (in_array($mode, SiteEnvironment::VISITOR_MODES, true)
            || ! in_array($this->environment->current()->environment, self::TEST_ENVIRONMENTS, true)) {
            return '';
        }

        return '<p class="corex-flow-notice corex-flow-notice--test">'
            . esc_html__('Test site: what you send here is kept for testing and no email is delivered.', 'corex')
            . '</p>';
    }
}
