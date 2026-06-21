<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\ControlPanel;

defined('ABSPATH') || exit;

/**
 * Computes the per-domain status of the Corex control panel (spec 044, US1) from the
 * already-stored settings values — pure, so it is headlessly testable and persists
 * nothing. A domain is `error` when a recorded test failed, else `needs_setup` when a
 * required field for the chosen feature is empty, else `configured`. Optional domains
 * (brand/forms/insights) are configured by default (Principle IX — optional keys do not
 * force "needs setup").
 */
final class ControlPanelStatus
{
    private const KEY_DRIVERS = ['recaptcha', 'turnstile', 'hcaptcha'];

    /**
     * @param array<string,mixed> $values      settings values keyed by Config dot-key
     * @param array<string,bool>  $failedTests domain => true when its last test failed
     *
     * @return list<DomainStatus>
     */
    public function domains(array $values, array $failedTests = []): array
    {
        return [
            $this->brand($values, $failedTests),
            $this->mail($values, $failedTests),
            $this->forms($values, $failedTests),
            $this->captcha($values, $failedTests),
            $this->insights($values, $failedTests),
        ];
    }

    private function brand(array $values, array $failed): DomainStatus
    {
        // Branding is optional — a site with no custom logo is still "configured".
        return $this->resolve('brand', __('Branding', 'corex'), [], $failed);
    }

    private function mail(array $values, array $failed): DomainStatus
    {
        $missing = $this->isEmpty($values, 'mail.from.address')
            ? [__('From address', 'corex')]
            : [];

        return $this->resolve('mail', __('Mail', 'corex'), $missing, $failed);
    }

    private function forms(array $values, array $failed): DomainStatus
    {
        // The notification recipient falls back to the admin email — optional.
        return $this->resolve('forms', __('Forms', 'corex'), [], $failed);
    }

    private function captcha(array $values, array $failed): DomainStatus
    {
        $driver  = (string) ($values['captcha.driver'] ?? 'none');
        $missing = [];

        if (in_array($driver, self::KEY_DRIVERS, true)) {
            if ($this->isEmpty($values, 'captcha.site_key')) {
                $missing[] = __('Site key', 'corex');
            }
            if ($this->isEmpty($values, 'captcha.secret')) {
                $missing[] = __('Secret key', 'corex');
            }
        }

        return $this->resolve('captcha', __('Captcha', 'corex'), $missing, $failed);
    }

    private function insights(array $values, array $failed): DomainStatus
    {
        // PageSpeed works keyless; the key is recommended, not required — optional.
        return $this->resolve('insights', __('Insights', 'corex'), [], $failed);
    }

    /**
     * @param list<string>       $missing
     * @param array<string,bool> $failed
     */
    private function resolve(string $domain, string $label, array $missing, array $failed): DomainStatus
    {
        if (! empty($failed[$domain])) {
            $status = DomainStatus::ERROR;
        } elseif ($missing !== []) {
            $status = DomainStatus::NEEDS_SETUP;
        } else {
            $status = DomainStatus::CONFIGURED;
        }

        return new DomainStatus(
            $domain,
            $label,
            $status,
            $missing,
            'admin.php?page=corex-settings-config#corex-settings-section-' . $domain,
        );
    }

    private function isEmpty(array $values, string $key): bool
    {
        return trim((string) ($values[$key] ?? '')) === '';
    }
}
