<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Saves the login settings a request changes, and only those (spec 124, FR-015, User Story 7).
 *
 * Every field the request sends is checked first ({@see LoginSettingsInput}), and a new login address
 * is asked whether anything on the site already answers there. One refused field refuses the whole
 * request and nothing is written, not even its valid fields, so the configuration that works now
 * keeps working.
 */
final class LoginSettingsSaver
{
    public function __construct(
        private readonly LoginProtectionSettingsStore $store,
        private readonly LoginSlugAvailability $availability,
    ) {
    }

    /**
     * @param array<string,mixed> $body the request's fields
     */
    public function save(array $body): LoginSettingsSaveOutcome
    {
        $current = $this->store->current();
        $input = LoginSettingsInput::read($body, $current);
        $problems = $input->problems;

        // `LoginSlug` says whether the address is well-formed and not one WordPress reserves;
        // availability says whether a published page or a route already answers there. `about` passes
        // the first and is a common page.
        $slug = $input->changes['custom_slug'] ?? null;
        $taken = is_string($slug) ? $this->availability->rejectionReason($slug) : null;
        if ($taken !== null) {
            $hiding = ($input->switches ?? $current->switches())->hideWpLogin;
            $problems['custom_slug'] = new LoginSettingsProblem(
                'custom_slug',
                $hiding ? LoginSettingsProblem::REASON_ADDRESS_NEEDED_TO_HIDE : LoginSettingsProblem::REASON_ADDRESS,
                detail: $taken,
            );
        }

        if ($problems !== []) {
            return LoginSettingsSaveOutcome::refused($problems);
        }

        try {
            return LoginSettingsSaveOutcome::saved($this->store->update($input->changes, $input->switches));
        } catch (LoginSettingsRefused $refused) {
            return LoginSettingsSaveOutcome::refused([$refused->field => LoginSettingsProblem::fromRefusal($refused)]);
        } catch (LoginSettingsBusy) {
            return LoginSettingsSaveOutcome::refused([
                'request' => new LoginSettingsProblem('request', LoginSettingsProblem::REASON_BUSY),
            ]);
        }
    }
}
