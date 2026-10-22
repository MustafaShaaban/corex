<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

use Corex\Config\Insights\InsightResult;
use Corex\Config\Security\LoginProtection\LoginAddressState;

defined('ABSPATH') || exit;

/**
 * Pure view model for the CoreX Overview (spec 064). It composes the approved dense readiness dashboard
 * from ALREADY-GATHERED, real facts (the corex-config boundary reads them from live WordPress): stat
 * tiles, a launch-readiness checklist, an analytics-and-security integrations panel, a data-sources
 * summary, and a forms summary. Every value is real or an honest state — it never fabricates a count,
 * an integration, a readiness score, or an activity feed; a fact that could not be read is "Could
 * not check", never a success (spec 114, US-SAFE-10). WordPress-free, so it is unit-testable.
 */
final class OverviewModel
{
    public const TONE_SUCCESS = 'success';
    public const TONE_WARNING = 'warning';
    public const TONE_INFO    = 'info';
    public const TONE_NEUTRAL = 'neutral';

    /** Where the brand is set: the Brand tab of Settings. */
    public const BRAND_SETTINGS = 'admin.php?page=corex-settings-config&corex_tab=brand';

    /** The captcha providers that need keys, by driver, as the Settings screen names them. */
    private const PROVIDERS = [
        'recaptcha' => 'reCAPTCHA',
        'turnstile' => 'Cloudflare Turnstile',
        'hcaptcha'  => 'hCaptcha',
    ];

    /**
     * @param array{
     *   counts: array{posts:int,pages:int,submissions:int|null,addonsActive:int,addonsTotal:int},
     *   facts: array{
     *     brand: array{name:bool,logo:bool},
     *     mailFrom: bool,
     *     insights: array{key:bool,latest:array<string,mixed>|null}|null,
     *     captcha: array{active:bool,driver:string,keys:bool}|null,
     *     login: string|null
     *   },
     *   frontPageSet: bool,
     *   kitApplied: bool,
     *   hardeningWarnings: int|null,
     *   dataSources: list<array{label:string,key:string,total:int}>,
     *   formsCount: int,
     *   flowsCount: int
     * } $facts
     *
     * @return array{
     *   tiles: list<array{label:string,value:string,detail:string}>,
     *   readiness: array{rows:list<array{label:string,note:string,done:bool,tone:string,href?:string}>,done:int,total:int},
     *   integrations: list<array{label:string,note:string,tone:string}>,
     *   dataSources: list<array{label:string,key:string,count:int}>,
     *   forms: array{count:int,flows:int,note:string}
     * }
     */
    public function build(array $facts): array
    {
        return [
            'tiles'        => $this->tiles($facts['counts']),
            'readiness'    => $this->readiness($facts['facts'], $facts['frontPageSet'], $facts['kitApplied'], $facts['hardeningWarnings']),
            'integrations' => $this->integrations($facts['facts']),
            'dataSources'  => $this->dataSources($facts['dataSources']),
            'forms'        => [
                'count' => max(0, $facts['formsCount']),
                'flows' => max(0, $facts['flowsCount'] ?? 0),
                'note'  => __('Every form CoreX knows about — visual flows and forms registered in code.', 'corex'),
            ],
        ];
    }

    /**
     * @param array{posts:int,pages:int,submissions:int|null,addonsActive:int,addonsTotal:int} $counts
     *
     * @return list<array{label:string,value:string,detail:string}>
     */
    private function tiles(array $counts): array
    {
        $submissions = $counts['submissions'];

        return [
            ['label' => __('Posts', 'corex'), 'value' => (string) max(0, $counts['posts']), 'detail' => __('Published', 'corex')],
            ['label' => __('Pages', 'corex'), 'value' => (string) max(0, $counts['pages']), 'detail' => __('Published', 'corex')],
            [
                'label'  => __('Submissions', 'corex'),
                'value'  => $submissions === null ? __('—', 'corex') : (string) max(0, $submissions),
                'detail' => $submissions === null ? __('Forms add-on inactive', 'corex') : __('Stored', 'corex'),
            ],
            [
                'label'  => __('Add-ons', 'corex'),
                /* translators: 1: active add-on count, 2: total installed */
                'value'  => sprintf(__('%1$d / %2$d', 'corex'), max(0, $counts['addonsActive']), max(0, $counts['addonsTotal'])),
                'detail' => __('Active / installed', 'corex'),
            ],
        ];
    }

    /**
     * A launch-readiness checklist built only from real configuration/provisioning signals. Each
     * row is done only when the fact it names is so (spec 114, US-SAFE-10).
     *
     * @param array<string,mixed> $facts
     *
     * @return array{rows:list<array{label:string,note:string,done:bool,tone:string,href?:string}>,done:int,total:int}
     */
    private function readiness(array $facts, bool $frontPageSet, bool $kitApplied, ?int $hardeningWarnings): array
    {
        $rows = [
            $this->brandRow($facts['brand']),
            $this->readyRow(__('Company Site Kit applied', 'corex'), $kitApplied, __('Starter pages provisioned', 'corex')),
            $this->readyRow(__('Front page set', 'corex'), $frontPageSet, __('A static front page is selected', 'corex')),
            $this->readyRow(__('Transactional email configured', 'corex'), $facts['mailFrom'], __('From address set', 'corex')),
            $this->spamRow($facts['captcha']),
            $this->hardeningRow($hardeningWarnings),
        ];

        $done = count(array_filter($rows, static fn (array $r): bool => $r['done']));

        return ['rows' => $rows, 'done' => $done, 'total' => count($rows)];
    }

    /**
     * @return array{label:string,note:string,done:bool,tone:string}
     */
    private function readyRow(string $label, bool $done, string $note): array
    {
        return [
            'label' => $label,
            'note'  => $done ? __('Done', 'corex') : $note,
            'done'  => $done,
            'tone'  => $done ? self::TONE_SUCCESS : self::TONE_WARNING,
        ];
    }

    /**
     * Done only with a company name and a logo. It was always done: branding was "optional".
     *
     * @param array{name:bool,logo:bool} $brand
     *
     * @return array{label:string,note:string,done:bool,tone:string,href?:string}
     */
    private function brandRow(array $brand): array
    {
        $row = $this->readyRow(__('Brand configured', 'corex'), $brand['name'] && $brand['logo'], __('Not set', 'corex'));

        return $row['done'] ? $row : $row + ['href' => self::BRAND_SETTINGS];
    }

    /**
     * Every CoreX form carries a honeypot, so spam protection is always there; the row says how
     * much of it. It read "Done" with "Captcha / honeypot ready" whatever was set.
     *
     * @param array{active:bool,driver:string,keys:bool}|null $captcha
     *
     * @return array{label:string,note:string,done:bool,tone:string}
     */
    private function spamRow(?array $captcha): array
    {
        $label = __('Spam protection', 'corex');

        if ($captcha === null) {
            return $this->notChecked($label);
        }

        $provider = $this->workingProvider($captcha);

        return [
            'label' => $label,
            'note'  => $provider === null
                ? __('Honeypot only', 'corex')
                /* translators: %s: a captcha provider, e.g. Cloudflare Turnstile */
                : sprintf(__('Honeypot and %s', 'corex'), $provider),
            'done'  => true,
            'tone'  => $provider === null ? self::TONE_INFO : self::TONE_SUCCESS,
        ];
    }

    /**
     * WordPress hardening, named as such. Its count stood in for "Login protection", which it
     * never read.
     *
     * @return array{label:string,note:string,done:bool,tone:string}
     */
    private function hardeningRow(?int $warnings): array
    {
        $label = __('Hardening checks', 'corex');

        if ($warnings === null) {
            return $this->notChecked($label);
        }

        return $this->readyRow(
            $label,
            $warnings === 0,
            sprintf(
                /* translators: %d: number of hardening checks needing attention */
                _n('%d check to review', '%d checks to review', $warnings, 'corex'),
                $warnings,
            ),
        );
    }

    /**
     * Analytics & security integrations, each in the words of what is true.
     *
     * @param array<string,mixed> $facts
     *
     * @return list<array{label:string,note:string,tone:string}>
     */
    private function integrations(array $facts): array
    {
        return [
            $this->insightsRow($facts['insights']),
            $this->captchaRow($facts['captcha']),
            [
                'label' => __('Transactional email', 'corex'),
                'note'  => $facts['mailFrom'] ? __('From address set', 'corex') : __('Not configured', 'corex'),
                'tone'  => $facts['mailFrom'] ? self::TONE_SUCCESS : self::TONE_NEUTRAL,
            ],
            $this->loginRow($facts['login']),
        ];
    }

    /**
     * "Connected" only with a saved key whose latest run completed. It read "Provider connected"
     * on every site, key or none.
     *
     * @param array{key:bool,latest:array<string,mixed>|null}|null $insights
     *
     * @return array{label:string,note:string,tone:string}
     */
    private function insightsRow(?array $insights): array
    {
        $label = __('Insights / PageSpeed', 'corex');

        if ($insights === null) {
            return $this->notCheckedIntegration($label);
        }

        $latest = $insights['latest'];
        if (($latest['status'] ?? '') === InsightResult::STATUS_FAILED) {
            return [
                'label' => $label,
                /* translators: %s: why the last PageSpeed run could not complete */
                'note'  => sprintf(__('Error: %s', 'corex'), (string) ($latest['summary'] ?? '')),
                'tone'  => self::TONE_WARNING,
            ];
        }

        if (! $insights['key']) {
            return ['label' => $label, 'note' => __('Keyless (shared quota)', 'corex'), 'tone' => self::TONE_NEUTRAL];
        }

        return $latest === null
            ? ['label' => $label, 'note' => __('Key saved, not checked yet', 'corex'), 'tone' => self::TONE_NEUTRAL]
            : ['label' => $label, 'note' => __('Connected', 'corex'), 'tone' => self::TONE_SUCCESS];
    }

    /**
     * It read "Configured" with the driver None, or with the captcha add-on off.
     *
     * @param array{active:bool,driver:string,keys:bool}|null $captcha
     *
     * @return array{label:string,note:string,tone:string}
     */
    private function captchaRow(?array $captcha): array
    {
        $label = __('Captcha', 'corex');

        if ($captcha === null) {
            return $this->notCheckedIntegration($label);
        }

        if (! $captcha['active']) {
            return ['label' => $label, 'note' => __('Not configured (add-on inactive)', 'corex'), 'tone' => self::TONE_NEUTRAL];
        }

        $name = self::PROVIDERS[$captcha['driver']] ?? null;
        if ($name === null) {
            return ['label' => $label, 'note' => __('Not configured', 'corex'), 'tone' => self::TONE_NEUTRAL];
        }

        if (! $captcha['keys']) {
            return ['label' => $label, 'note' => __('Keys missing', 'corex'), 'tone' => self::TONE_WARNING];
        }

        return [
            'label' => $label,
            /* translators: %s: a captcha provider, e.g. hCaptcha */
            'note'  => sprintf(__('%s configured', 'corex'), $name),
            'tone'  => self::TONE_SUCCESS,
        ];
    }

    /**
     * The Security Center's own answer: protection on or off, and whether the login address is
     * hidden. It counted hardening warnings instead.
     *
     * @return array{label:string,note:string,tone:string}
     */
    private function loginRow(?string $state): array
    {
        $label = __('Login protection', 'corex');

        return match ($state) {
            null                        => $this->notCheckedIntegration($label),
            LoginAddressState::OFF      => ['label' => $label, 'note' => __('Off', 'corex'), 'tone' => self::TONE_WARNING],
            LoginAddressState::HIDDEN   => ['label' => $label, 'note' => __('On, login address hidden', 'corex'), 'tone' => self::TONE_SUCCESS],
            LoginAddressState::RECOVERY => ['label' => $label, 'note' => __('On, recovery active: login address reachable', 'corex'), 'tone' => self::TONE_WARNING],
            default                     => ['label' => $label, 'note' => __('On, login address reachable', 'corex'), 'tone' => self::TONE_INFO],
        };
    }

    /**
     * The provider an active captcha add-on uses with both keys, or null.
     *
     * @param array{active:bool,driver:string,keys:bool} $captcha
     */
    private function workingProvider(array $captcha): ?string
    {
        $name = self::PROVIDERS[$captcha['driver']] ?? null;

        return $captcha['active'] && $captcha['keys'] ? $name : null;
    }

    /**
     * @return array{label:string,note:string,done:bool,tone:string}
     */
    private function notChecked(string $label): array
    {
        return ['label' => $label, 'note' => __('Could not check', 'corex'), 'done' => false, 'tone' => self::TONE_WARNING];
    }

    /**
     * @return array{label:string,note:string,tone:string}
     */
    private function notCheckedIntegration(string $label): array
    {
        return ['label' => $label, 'note' => __('Could not check', 'corex'), 'tone' => self::TONE_WARNING];
    }

    /**
     * @param list<array{label:string,key:string,total:int}> $sources
     *
     * @return list<array{label:string,key:string,count:int}>
     */
    private function dataSources(array $sources): array
    {
        $out = [];
        foreach ($sources as $source) {
            $out[] = ['label' => $source['label'], 'key' => $source['key'], 'count' => max(0, $source['total'])];
        }

        return $out;
    }
}
