<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Readiness;

defined('ABSPATH') || exit;

use Corex\Config\Operations\ReadinessSnapshot;

/**
 * The "Site and content" checks of the launch checklist (spec 118, US-OVR-03), from facts already
 * read by {@see SiteReadinessFacts}. They advise: a check that is not met is pending and never
 * blocks Production (OVR-D4). A fact that could not be read arrives as null and the check says
 * "Could not check". Each open check says what is missing and names an admin path where it is
 * fixed. WordPress-free apart from translation, so it is unit-testable.
 */
final class SiteChecks
{
    public const BRAND_SETTINGS = 'admin.php?page=corex-settings-config&corex_tab=brand';

    /** A test email counts for this many days (US-OVR-03). */
    public const TEST_EMAIL_DAYS = 90;

    /**
     * @param array{
     *   brand: array{name:bool,logo:bool}|null,
     *   kit: array{present:bool,applied:bool}|null,
     *   frontPage: array{static:bool,template:bool}|null,
     *   privacy: array{page:int,published:bool}|null,
     *   terms: array{page:int,published:bool}|null,
     *   testEmail: array{mail:bool,sent:bool}|null,
     *   login: bool|null
     * } $facts
     *
     * @return list<array{key:string,label:string,state:string,summary:string,resolution_url:string}>
     */
    public function checks(array $facts): array
    {
        $checks = [$this->brand($facts['brand'])];

        // The kit check exists only while a kit add-on is (US-OVR-01): Muva runs without one.
        if ($facts['kit'] === null || $facts['kit']['present']) {
            $checks[] = $this->kit($facts['kit']);
        }

        return [
            ...$checks,
            $this->frontPage($facts['frontPage']),
            $this->page('privacy_page', $facts['privacy'], [
                'label'    => __('Privacy page published', 'corex'),
                'unchosen' => __('No privacy policy page chosen', 'corex'),
                'chooseAt' => 'options-privacy.php',
                'draft'    => __('The privacy policy page is not published', 'corex'),
            ]),
            $this->page('terms_page', $facts['terms'], [
                'label'    => __('Terms page published', 'corex'),
                'unchosen' => __('Choose a terms page', 'corex'),
                'chooseAt' => self::BRAND_SETTINGS,
                'draft'    => __('The terms page is not published', 'corex'),
            ]),
            $this->testEmail($facts['testEmail']),
            $this->login($facts['login']),
        ];
    }

    /**
     * @param array{name:bool,logo:bool}|null $brand
     *
     * @return array{key:string,label:string,state:string,summary:string,resolution_url:string}
     */
    private function brand(?array $brand): array
    {
        $label = __('Brand set', 'corex');

        if ($brand === null) {
            return $this->unchecked('brand', $label, self::BRAND_SETTINGS);
        }

        $missing = match (true) {
            ! $brand['name'] && ! $brand['logo'] => __('No company name or admin logo', 'corex'),
            ! $brand['name']                     => __('No company name', 'corex'),
            ! $brand['logo']                     => __('No admin logo', 'corex'),
            default                              => '',
        };

        return $this->row('brand', $label, $missing, __('Company name and admin logo set', 'corex'), self::BRAND_SETTINGS);
    }

    /**
     * @param array{present:bool,applied:bool}|null $kit
     *
     * @return array{key:string,label:string,state:string,summary:string,resolution_url:string}
     */
    private function kit(?array $kit): array
    {
        $label = __('Kit applied', 'corex');
        $fix   = 'admin.php?page=corex-setup';

        if ($kit === null) {
            return $this->unchecked('kit', $label, $fix);
        }

        return $this->row(
            'kit',
            $label,
            $kit['applied'] ? '' : __('The kit\'s starter pages are not applied yet', 'corex'),
            __('The kit\'s starter pages are applied', 'corex'),
            $fix,
        );
    }

    /**
     * Passes with a static front page, or with a theme that draws its own front page (US-OVR-01).
     *
     * @param array{static:bool,template:bool}|null $front
     *
     * @return array{key:string,label:string,state:string,summary:string,resolution_url:string}
     */
    private function frontPage(?array $front): array
    {
        $label = __('Front page', 'corex');
        $fix   = 'options-reading.php';

        if ($front === null) {
            return $this->unchecked('front_page', $label, $fix);
        }

        $done = match (true) {
            $front['static']   => __('A static front page is chosen', 'corex'),
            $front['template'] => __('The theme has its own front-page template', 'corex'),
            default            => '',
        };

        return $this->row(
            'front_page',
            $label,
            $done === '' ? __('No static front page or front-page template', 'corex') : '',
            $done,
            $fix,
        );
    }

    /**
     * A chosen page that must be published: unchosen is fixed where it is chosen, a draft where it is
     * edited.
     *
     * @param array{page:int,published:bool}|null                          $page
     * @param array{label:string,unchosen:string,chooseAt:string,draft:string} $words
     *
     * @return array{key:string,label:string,state:string,summary:string,resolution_url:string}
     */
    private function page(string $key, ?array $page, array $words): array
    {
        if ($page === null) {
            return $this->unchecked($key, $words['label'], $words['chooseAt']);
        }

        if ($page['page'] < 1) {
            return $this->row($key, $words['label'], $words['unchosen'], '', $words['chooseAt']);
        }

        return $this->row(
            $key,
            $words['label'],
            $page['published'] ? '' : $words['draft'],
            __('Chosen and published', 'corex'),
            $page['published'] ? $words['chooseAt'] : 'post.php?post=' . $page['page'] . '&action=edit',
        );
    }

    /**
     * @param array{mail:bool,sent:bool}|null $email
     *
     * @return array{key:string,label:string,state:string,summary:string,resolution_url:string}
     */
    private function testEmail(?array $email): array
    {
        $label = __('Test email sent', 'corex');
        $fix   = 'admin.php?page=corex-email-studio';

        if ($email === null) {
            return $this->unchecked('test_email', $label, $fix);
        }

        // Without Corex Mail there is no test to send: the row says what it needs and is not counted.
        if (! $email['mail']) {
            return [
                'key'            => 'test_email',
                'label'          => $label,
                'state'          => ReadinessSnapshot::NOT_APPLICABLE,
                'summary'        => __('Needs Corex Mail', 'corex'),
                'resolution_url' => 'admin.php?page=corex-addons',
            ];
        }

        return $this->row(
            'test_email',
            $label,
            $email['sent'] ? '' : sprintf(
                /* translators: %d: number of days, 90. */
                _n('No test email sent in the last %d day', 'No test email sent in the last %d days', self::TEST_EMAIL_DAYS, 'corex'),
                self::TEST_EMAIL_DAYS,
            ),
            sprintf(
                /* translators: %d: number of days, 90. */
                _n('A test email was sent in the last %d day', 'A test email was sent in the last %d days', self::TEST_EMAIL_DAYS, 'corex'),
                self::TEST_EMAIL_DAYS,
            ),
            $fix,
        );
    }

    /**
     * @return array{key:string,label:string,state:string,summary:string,resolution_url:string}
     */
    private function login(?bool $enabled): array
    {
        $label = __('Login protection on', 'corex');
        $fix   = 'admin.php?page=corex-operations-security&tab=login';

        if ($enabled === null) {
            return $this->unchecked('login_protection', $label, $fix);
        }

        return $this->row(
            'login_protection',
            $label,
            $enabled ? '' : __('Login protection is off', 'corex'),
            __('Login protection is on', 'corex'),
            $fix,
        );
    }

    /**
     * Done when nothing is missing; otherwise pending, saying what is missing.
     *
     * @return array{key:string,label:string,state:string,summary:string,resolution_url:string}
     */
    private function row(string $key, string $label, string $missing, string $done, string $fix): array
    {
        return [
            'key'            => $key,
            'label'          => $label,
            'state'          => $missing === '' ? ReadinessSnapshot::PASS : ReadinessSnapshot::WARNING,
            'summary'        => $missing === '' ? $done : $missing,
            'resolution_url' => $fix,
        ];
    }

    /**
     * @return array{key:string,label:string,state:string,summary:string,resolution_url:string}
     */
    private function unchecked(string $key, string $label, string $fix): array
    {
        return [
            'key'            => $key,
            'label'          => $label,
            'state'          => ReadinessSnapshot::UNAVAILABLE,
            'summary'        => __('This could not be checked.', 'corex'),
            'resolution_url' => $fix,
        ];
    }
}
