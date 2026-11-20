<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Environment\SiteEnvironment;
use Corex\Forms\Flow\FlowNotice;
use Corex\Http\PublicSubmissionPause;
use Corex\Mail\Capture\MailDecision;
use Corex\Mail\Capture\MailGate;
use DateTimeImmutable;

/**
 * What the site's mode tells a visitor at a flow (spec 124, FR-021, User Story 10).
 *
 * A development or staging site says it is a test copy, and what the mail gate does with this
 * form's emails there: delivers them, keeps them on the site, or, with nowhere to keep them, does
 * not deliver them (DECISIONS #523, #650). The gate is asked as a display query, so nothing is sent. While forms are paused a visitor is told so in the form's place, in the words
 * the refused submission carries, with when they are back; who passes is {@see PublicSubmissionPause}'s answer, the same
 * gate that refuses the submission itself, so an administrator gets the real form.
 */
final readonly class SiteModeNotice
{
    public function __construct(
        private SiteEnvironment $environment,
        private PublicSubmissionPause $pause,
        private MailGate $mail,
    ) {
    }

    /** The test-site notice on a development or staging site; null on any other. */
    public function testSite(): ?FlowNotice
    {
        if ($this->environment->current()->isProduction()) {
            return null;
        }

        // A form's emails are CoreX's own, so the "Capture only CoreX mail" switch keeps them too.
        return new FlowNotice(
            __('Test site', 'corex'),
            match ($this->mail->decide(true)) {
                MailDecision::Deliver => __("This is a test copy of the site. What you send here is kept for testing. This form's emails are delivered.", 'corex'),
                // Nowhere to keep them (CoreX Mail is not active): they are refused, not kept.
                MailDecision::Refuse => __("This is a test copy of the site. What you send here is kept for testing. This form's emails are not delivered.", 'corex'),
                default => __("This is a test copy of the site. What you send here is kept for testing. This form's emails stay on the site and are not delivered.", 'corex'),
            },
        );
    }

    /** The paused notice while forms are paused for this visitor; null while they are open to them. */
    public function paused(): ?FlowNotice
    {
        if ($this->pause->closedBy() !== PublicSubmissionPause::FORMS_PAUSED) {
            return null;
        }

        $paused = $this->pause->pausedNotice(new DateTimeImmutable('now'));

        return new FlowNotice($paused['title'], $paused['message'], when: $paused['when']);
    }
}
