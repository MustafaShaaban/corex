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
        $problems = [];

        try {
            // Decided from the settings as read under the lock, not before it: a switch the request
            // did not send keeps what another save wrote meanwhile, and "a value equal to the one in
            // force is no change" is judged against what is in force when this one writes.
            $saved = $this->store->updateWith(function (LoginProtectionSettings $fresh) use ($body, &$problems): ?array {
                $input = LoginSettingsInput::read($body, $fresh);
                $problems = $input->problems + $this->taken($input, $fresh);

                return $problems === [] ? [$input->changes, $input->switches] : null;
            });
        } catch (LoginSettingsRefused $refused) {
            return LoginSettingsSaveOutcome::refused([$refused->field => LoginSettingsProblem::fromRefusal($refused)]);
        } catch (LoginSettingsBusy) {
            return LoginSettingsSaveOutcome::refused([
                'request' => new LoginSettingsProblem('request', LoginSettingsProblem::REASON_BUSY),
            ]);
        }

        return $problems === [] ? LoginSettingsSaveOutcome::saved($saved) : LoginSettingsSaveOutcome::refused($problems);
    }

    /**
     * A new address that a published page or a route already answers at. `LoginSlug` says whether
     * the address is well-formed and not one WordPress reserves; this says whether anything on the
     * site has it. `about` passes the first and is a common page.
     *
     * @return array<string,LoginSettingsProblem>
     */
    private function taken(LoginSettingsInput $input, LoginProtectionSettings $fresh): array
    {
        $slug = $input->changes['custom_slug'] ?? null;
        $taken = is_string($slug) && ! isset($input->problems['custom_slug']) ? $this->availability->rejectionReason($slug) : null;

        if ($taken === null) {
            return [];
        }

        return ['custom_slug' => new LoginSettingsProblem(
            'custom_slug',
            ($input->switches ?? $fresh->switches())->hideWpLogin
                ? LoginSettingsProblem::REASON_ADDRESS_NEEDED_TO_HIDE
                : LoginSettingsProblem::REASON_ADDRESS,
            detail: $taken,
        )];
    }
}
