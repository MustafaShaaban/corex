<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Support;

defined('ABSPATH') || exit;

use Corex\Admin\AdminPage;

/**
 * The "still stuck?" panel at the foot of the Guides screen (spec 087, FR-001 / FR-004 / FR-009).
 *
 * It renders last on purpose. Somebody who found their answer never reaches it; somebody who
 * scrolled to the bottom of every guide without finding one is exactly who it is for.
 *
 * Server-rendered strings, like the rest of this add-on — there is no build step here and adding
 * one to draw a form with four controls would be a bundle shipped to render text the server already
 * has (spec 084's reasoning, unchanged).
 */
final class SupportPanel
{
    public function __construct(
        private readonly SupportSettings $settings,
        private readonly SupportFlash $flash,
        private readonly AdminPage $page,
    ) {
    }

    public function render(): string
    {
        // Nothing is read before this point. `take()` deletes as it reads, so taking the flash
        // first and then returning early would consume somebody's result and show it to nobody —
        // and the case where that matters most is the one where the address was just removed.
        if (! $this->settings->enabled()) {
            return '';
        }

        $flash = $this->flash->take(get_current_user_id());

        // Configured but with no deliverable address: say so rather than draw a form whose submit
        // would throw the message away. The two are the same fact to a reader — there is nobody at
        // the other end — and only the fix differs.
        if (! $this->settings->configured()) {
            return $this->page->state(
                'info',
                __('Support is not set up on this site', 'corex'),
                __('Nobody has given this site a support address yet, so there is nowhere for a message to go. An administrator can add one in CoreX Settings, under Guides.', 'corex'),
            );
        }

        return '<section class="corex-guides__support" id="corex-guides-support">'
            . '<h2 class="corex-guides__support-title">' . esc_html__('Still stuck?', 'corex') . '</h2>'
            . '<p class="corex-guides__support-intro">'
            . esc_html__('If the guides did not answer it, or something here is wrong or missing, tell us. A person reads these.', 'corex')
            . '</p>'
            . $this->flashHtml($flash)
            . $this->formHtml($flash['message'] ?? '')
            . '</section>';
    }

    /** @param array{outcome:string,message:string}|null $flash */
    private function flashHtml(?array $flash): string
    {
        if ($flash === null) {
            return '';
        }

        [$tone, $title, $message] = match ($flash['outcome']) {
            SupportFlash::SENT => [
                'success',
                __('Message sent', 'corex'),
                __('Thanks — it is on its way. If you left an email address, any reply goes there.', 'corex'),
            ],
            SupportFlash::THROTTLED => [
                'warning',
                __('That was just sent', 'corex'),
                __('Give it a minute before sending another, so the same message does not arrive twice.', 'corex'),
            ],
            SupportFlash::EMPTY_MESSAGE => [
                'warning',
                __('Nothing to send', 'corex'),
                __('Write what you were looking for, or what went wrong, and send it again.', 'corex'),
            ],
            SupportFlash::UNAVAILABLE => [
                'info',
                __('Support is not set up on this site', 'corex'),
                __('There is no support address configured, so the message was not sent. An administrator can add one in CoreX Settings, under Guides.', 'corex'),
            ],
            // Never "sent" for something that was not. What was typed comes back with the form
            // below, so the second attempt is a click rather than a retype (FR-007).
            default => [
                'error',
                __('That did not send', 'corex'),
                __('The site could not deliver the message. Your text is still in the box — try again, and tell an administrator if it keeps failing.', 'corex'),
            ],
        };

        return $this->page->state($tone, $title, $message);
    }

    private function formHtml(string $message): string
    {
        $user = wp_get_current_user();
        $email = is_email((string) $user->user_email) ? (string) $user->user_email : '';

        return '<form class="corex-guides__support-form" method="post" action="'
            . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="' . esc_attr(SupportRequestController::ACTION) . '" />'
            . wp_nonce_field(SupportRequestController::ACTION, SupportRequestController::NONCE, true, false)
            . $this->honeypotHtml()
            . $this->categoryHtml()
            . '<label class="corex-guides__support-field" for="corex-guides-support-message">'
            . '<span>' . esc_html__('What do you need?', 'corex') . '</span>'
            . '<textarea id="corex-guides-support-message" name="corex_guides_message" rows="5" required>'
            . esc_textarea($message) . '</textarea></label>'
            . '<label class="corex-guides__support-field" for="corex-guides-support-email">'
            . '<span>' . esc_html__('Your email, so we can reply', 'corex') . '</span>'
            . '<input type="email" id="corex-guides-support-email" name="corex_guides_email" value="'
            . esc_attr($email) . '" autocomplete="email" /></label>'
            . '<button type="submit" class="button button-primary">'
            . esc_html__('Send to support', 'corex') . '</button>'
            . '</form>';
    }

    private function categoryHtml(): string
    {
        $options = '';

        foreach (SupportRequestController::categories() as $key => $label) {
            $options .= '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
        }

        return '<label class="corex-guides__support-field" for="corex-guides-support-category">'
            . '<span>' . esc_html__('What is this about?', 'corex') . '</span>'
            . '<select id="corex-guides-support-category" name="corex_guides_category" data-corex-select>'
            . $options . '</select></label>';
    }

    /**
     * A field a person never sees and a naive bot always fills.
     *
     * Hidden with an inline style rather than a class because it must stay hidden even if this
     * add-on's stylesheet fails to load — a visible "Website" box on a support form is a question
     * somebody would answer, and answering it silently discards their message.
     */
    private function honeypotHtml(): string
    {
        return '<p class="corex-guides__support-trap" style="display:none" aria-hidden="true">'
            . '<label>' . esc_html__('Website', 'corex')
            . '<input type="text" name="corex_guides_website" tabindex="-1" autocomplete="off" value="" />'
            . '</label></p>';
    }
}
