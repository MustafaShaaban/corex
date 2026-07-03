<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

use DomainException;

/**
 * Registry for CoreX-owned abilities. Platform capabilities remain outside this boundary.
 */
final class CorexAbilityCatalog
{
    /** @var array<string,CorexAbility> */
    private array $abilities = [];

    public static function defaults(): self
    {
        $catalog = new self();

        foreach (self::definitions() as $ability) {
            $catalog->register($ability);
        }

        return $catalog;
    }

    public function register(CorexAbility $ability): void
    {
        if (isset($this->abilities[$ability->key])) {
            throw new DomainException(sprintf('CoreX ability "%s" is already registered.', $ability->key));
        }

        $this->abilities[$ability->key] = $ability;
    }

    public function find(string $key): ?CorexAbility
    {
        return $this->abilities[$key] ?? null;
    }

    /** @return list<CorexAbility> */
    public function all(): array
    {
        return array_values($this->abilities);
    }

    /** @return array<string,list<CorexAbility>> */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->abilities as $ability) {
            $groups[$ability->group][] = $ability;
        }

        return $groups;
    }

    /**
     * @param list<string> $keys
     *
     * @return list<string>
     */
    public function expanded(array $keys): array
    {
        $expanded = [];
        $pending  = $keys;

        while ($pending !== []) {
            $key = array_shift($pending);

            if (in_array($key, $expanded, true)) {
                continue;
            }

            $ability = $this->find($key);
            if ($ability === null) {
                throw new DomainException(sprintf('CoreX ability "%s" is not registered.', $key));
            }

            $expanded[] = $key;
            array_push($pending, ...$ability->implies);
        }

        return $expanded;
    }

    /** @return list<CorexAbility> */
    private static function definitions(): array
    {
        $allAreaAbilities = [
            CorexAbility::MANAGE_ACCESS,
            CorexAbility::MANAGE_FORMS,
            CorexAbility::MANAGE_SUBMISSIONS,
            CorexAbility::MANAGE_DATA,
            CorexAbility::MANAGE_DATA_MODELS,
            CorexAbility::MANAGE_EMAIL,
            CorexAbility::MANAGE_BLOG,
            CorexAbility::MANAGE_OPERATIONS,
            CorexAbility::RUN_DANGEROUS_ACTIONS,
            CorexAbility::MANAGE_SETUP,
            CorexAbility::MANAGE_SETTINGS,
        ];

        return [
            self::ability(
                CorexAbility::MANAGE_ADMIN,
                'Access CoreX admin',
                'Access every CoreX product area.',
                CorexAbility::GROUP_ADMIN,
                CorexAbility::RISK_CRITICAL,
                true,
                $allAreaAbilities,
                ['corex-settings'],
                [],
            ),
            self::ability(
                CorexAbility::MANAGE_ACCESS,
                'Manage access',
                'Grant and review CoreX abilities.',
                CorexAbility::GROUP_ADMIN,
                CorexAbility::RISK_CRITICAL,
                false,
                [],
                ['corex-access'],
                ['access.grant', 'access.revoke', 'access.decide'],
            ),
            self::ability(
                CorexAbility::MANAGE_FORMS,
                'Manage forms and flows',
                'Create, publish, and operate forms and flows.',
                CorexAbility::GROUP_FORMS,
                CorexAbility::RISK_SENSITIVE,
                false,
                [],
                ['corex-forms'],
                ['forms.save', 'forms.publish'],
            ),
            self::ability(
                CorexAbility::MANAGE_SUBMISSIONS,
                'Manage submissions',
                'Read and process form submissions.',
                CorexAbility::GROUP_SUBMISSIONS,
                CorexAbility::RISK_SENSITIVE,
                false,
                [],
                ['corex-submissions'],
                ['submissions.update', 'submissions.export'],
            ),
            self::ability(
                CorexAbility::MANAGE_DATA,
                'Manage data',
                'Explore, export, and change supported CoreX data.',
                CorexAbility::GROUP_DATA,
                CorexAbility::RISK_DANGEROUS,
                false,
                [],
                ['corex-data'],
                ['data.write', 'data.export'],
            ),
            self::ability(
                CorexAbility::MANAGE_DATA_MODELS,
                'Manage data models',
                'Import, migrate, and configure CoreX data models.',
                CorexAbility::GROUP_DATA_MODELS,
                CorexAbility::RISK_DANGEROUS,
                false,
                [],
                ['corex-data-models'],
                ['models.import', 'models.migrate'],
            ),
            self::ability(
                CorexAbility::MANAGE_EMAIL,
                'Manage Email Studio',
                'Configure, test, and inspect CoreX email.',
                CorexAbility::GROUP_EMAIL,
                CorexAbility::RISK_SENSITIVE,
                false,
                [],
                ['corex-email'],
                ['email.save', 'email.test', 'email.resend'],
            ),
            self::ability(
                CorexAbility::MANAGE_BLOG,
                'Manage Blog Pro',
                'Configure editorial and analytics workflows.',
                CorexAbility::GROUP_BLOG,
                CorexAbility::RISK_SENSITIVE,
                false,
                [],
                ['corex-blog'],
                ['blog.configure'],
            ),
            self::ability(
                CorexAbility::MANAGE_OPERATIONS,
                'Manage operations and security',
                'Configure operating modes, security, and recovery.',
                CorexAbility::GROUP_OPERATIONS,
                CorexAbility::RISK_CRITICAL,
                false,
                [],
                ['corex-operations-security'],
                ['operations.save', 'security.save'],
            ),
            self::ability(
                CorexAbility::RUN_DANGEROUS_ACTIONS,
                'Run dangerous actions',
                'Confirm production-impacting and destructive operations.',
                CorexAbility::GROUP_OPERATIONS,
                CorexAbility::RISK_CRITICAL,
                false,
                [],
                [],
                ['operations.dangerous'],
            ),
            self::ability(
                CorexAbility::MANAGE_SETUP,
                'Manage setup',
                'Run and resume the CoreX setup workflow.',
                CorexAbility::GROUP_SETUP,
                CorexAbility::RISK_DANGEROUS,
                false,
                [],
                ['corex-setup'],
                ['setup.apply'],
            ),
            self::ability(
                CorexAbility::MANAGE_SETTINGS,
                'Manage settings',
                'Configure CoreX product settings.',
                CorexAbility::GROUP_SETTINGS,
                CorexAbility::RISK_SENSITIVE,
                false,
                [],
                ['corex-settings-config'],
                ['settings.save'],
            ),
        ];
    }

    /** @param list<string> $implies @param list<string> $screenSlugs @param list<string> $actionKeys */
    private static function ability(
        string $key,
        string $label,
        string $description,
        string $group,
        string $risk,
        bool $locked,
        array $implies,
        array $screenSlugs,
        array $actionKeys,
    ): CorexAbility {
        return new CorexAbility($key, $label, $description, $group, $risk, $locked, $implies, $screenSlugs, $actionKeys);
    }
}
