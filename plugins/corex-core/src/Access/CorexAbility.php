<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Immutable definition of one CoreX-owned product permission.
 */
final class CorexAbility
{
    public const MANAGE_ADMIN          = 'corex_manage_admin';
    public const MANAGE_ACCESS         = 'corex_manage_access';
    public const MANAGE_FORMS          = 'corex_manage_forms';
    public const MANAGE_SUBMISSIONS    = 'corex_manage_submissions';
    public const MANAGE_DATA           = 'corex_manage_data';
    public const MANAGE_DATA_MODELS    = 'corex_manage_data_models';
    public const MANAGE_EMAIL          = 'corex_manage_email';
    public const MANAGE_BLOG           = 'corex_manage_blog';
    public const MANAGE_OPERATIONS     = 'corex_manage_operations';
    public const MANAGE_NOTIFICATIONS  = 'corex_manage_notifications';
    public const RUN_DANGEROUS_ACTIONS = 'corex_run_dangerous_actions';
    public const MANAGE_SETUP          = 'corex_manage_setup';
    public const MANAGE_SETTINGS       = 'corex_manage_settings';

    public const GROUP_ADMIN       = 'corex-admin';
    public const GROUP_FORMS       = 'forms-flows';
    public const GROUP_SUBMISSIONS = 'submissions';
    public const GROUP_DATA        = 'data';
    public const GROUP_DATA_MODELS = 'data-models';
    public const GROUP_EMAIL       = 'email-studio';
    public const GROUP_BLOG        = 'blog-pro';
    public const GROUP_OPERATIONS  = 'operations-security';
    public const GROUP_NOTIFICATIONS = 'notifications';
    public const GROUP_SETUP       = 'setup-wizard';
    public const GROUP_SETTINGS    = 'settings';

    public const RISK_NORMAL    = 'normal';
    public const RISK_SENSITIVE = 'sensitive';
    public const RISK_DANGEROUS = 'dangerous';
    public const RISK_CRITICAL  = 'critical';

    private const GROUPS = [
        self::GROUP_ADMIN,
        self::GROUP_FORMS,
        self::GROUP_SUBMISSIONS,
        self::GROUP_DATA,
        self::GROUP_DATA_MODELS,
        self::GROUP_EMAIL,
        self::GROUP_BLOG,
        self::GROUP_OPERATIONS,
        self::GROUP_NOTIFICATIONS,
        self::GROUP_SETUP,
        self::GROUP_SETTINGS,
    ];

    private const RISKS = [self::RISK_NORMAL, self::RISK_SENSITIVE, self::RISK_DANGEROUS, self::RISK_CRITICAL];

    /**
     * @param list<string> $implies
     * @param list<string> $screenSlugs
     * @param list<string> $actionKeys
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly string $group,
        public readonly string $risk,
        public readonly bool $locked,
        public readonly array $implies,
        public readonly array $screenSlugs,
        public readonly array $actionKeys,
    ) {
        if (preg_match('/^corex_[a-z0-9_]+$/', $this->key) !== 1) {
            throw new InvalidArgumentException('CoreX ability keys must use the corex_ namespace.');
        }

        if ($this->label === '' || $this->description === '') {
            throw new InvalidArgumentException('CoreX abilities require a label and description.');
        }

        if (! in_array($this->group, self::GROUPS, true) || ! in_array($this->risk, self::RISKS, true)) {
            throw new InvalidArgumentException('CoreX ability group or risk is invalid.');
        }

        foreach ($this->implies as $implied) {
            if (preg_match('/^corex_[a-z0-9_]+$/', $implied) !== 1 || $implied === $this->key) {
                throw new InvalidArgumentException('CoreX ability implications must reference another CoreX ability.');
            }
        }
    }
}
