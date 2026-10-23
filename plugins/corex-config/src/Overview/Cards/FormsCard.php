<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview\Cards;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Config\Overview\OverviewCard;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Config\Submissions\SubmissionQueryService;
use Corex\Container\ContainerInterface;
use Corex\Forms\Catalog\FormCatalog;
use Corex\Multisite\PluginActivationInspector;
use Corex\Security\Admin\AdminGuard;

/**
 * The Overview's Forms & Flows card (spec 118, US-OVR-05): whether the inbox needs the viewer
 * today. Three numbers, Open (new or in progress), Assigned to you and Unread (open and not read),
 * each counted by the inbox's own query, so tests, the trash and spam are left out exactly as the
 * inbox leaves them out, and each links to the inbox with that filter.
 *
 * The forms add-on is optional (Principle IX): with it off the card says so. Its catalog is read
 * through the container for the same reason. A count that fails is not caught here: the card then
 * says "Could not load" (US-OVR-06).
 */
final class FormsCard implements OverviewCard
{
    public const FORMS_PLUGIN = 'corex-forms/corex-forms.php';

    public function __construct(
        private readonly SubmissionQueryService $queries,
        private readonly AdminGuard $guard,
        private readonly PluginActivationInspector $plugins,
        private readonly ContainerInterface $container,
    ) {
    }

    public function key(): string
    {
        return 'forms';
    }

    public function title(): string
    {
        return __('Forms & Flows', 'corex');
    }

    public function render(array $settingValues): string
    {
        if (! $this->plugins->isActive(self::FORMS_PLUGIN)) {
            return $this->card(
                '<p class="corex-overview__muted">' . esc_html__('Forms are off.', 'corex') . '</p>',
                'admin.php?page=corex-addons',
                __('Open Add-ons', 'corex'),
            );
        }

        /** @var FormCatalog $catalog */
        $catalog = $this->container->make(FormCatalog::class);
        $forms   = count($catalog->all());
        $pill    = '<span class="corex-overview__pill">' . esc_html(sprintf(
            /* translators: %s: number of forms. */
            _n('%s form', '%s forms', $forms, 'corex'),
            number_format_i18n($forms),
        )) . '</span>';

        if (! $this->guard->authorized(CorexAbility::MANAGE_SUBMISSIONS)) {
            return $this->card(
                '<p class="corex-overview__muted">' . esc_html__('Submission numbers are shown to people who may open the Submissions inbox.', 'corex') . '</p>',
                'admin.php?page=corex-forms',
                __('Open Forms & Flows', 'corex'),
                $pill,
            );
        }

        $actor = get_current_user_id();
        if ($this->count($actor, []) === 0) {
            return $this->card(
                '<p class="corex-overview__muted">' . esc_html__('No submissions yet.', 'corex') . '</p>',
                'admin.php?page=corex-forms',
                __('Open Forms & Flows', 'corex'),
                $pill,
            );
        }

        return $this->card($this->numbers($actor), 'admin.php?page=corex-submissions', __('Open the inbox', 'corex'), $pill);
    }

    /**
     * Open, Assigned to you and Unread, each a link to the inbox with the filter it counted.
     */
    private function numbers(int $actor): string
    {
        $numbers = [
            ['label' => __('Open', 'corex'), 'filter' => ['status' => SubmissionInboxQuery::OPEN]],
            ['label' => __('Assigned to you', 'corex'), 'filter' => ['status' => SubmissionInboxQuery::OPEN, 'owner' => 'user:' . $actor]],
            ['label' => __('Unread', 'corex'), 'filter' => ['status' => SubmissionInboxQuery::OPEN, 'unread' => '1']],
        ];

        $items = '';
        foreach ($numbers as $number) {
            $href   = add_query_arg(['page' => 'corex-submissions'] + $number['filter'], admin_url('admin.php'));
            $items .= '<li><a class="corex-overview__number" href="' . esc_url($href) . '">'
                . '<span class="corex-overview__number-value">' . esc_html(number_format_i18n($this->count($actor, $number['filter']))) . '</span>'
                . '<span class="corex-overview__number-label">' . esc_html($number['label']) . '</span></a></li>';
        }

        return '<ul class="corex-overview__numbers">' . $items . '</ul>';
    }

    /** @param array<string,string> $filter */
    private function count(int $actor, array $filter): int
    {
        return (int) $this->queries->query($actor, SubmissionInboxQuery::from($filter + ['per_page' => 1]))['total'];
    }

    private function card(string $body, string $link, string $linkText, string $pill = ''): string
    {
        return '<section class="corex-surface corex-overview__card corex-overview__card--compact" data-card="forms">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html($this->title()) . '</h2>' . $pill . '</header>'
            . $body
            . '<p><a href="' . esc_url(admin_url($link)) . '">' . esc_html($linkText) . '</a></p></section>';
    }
}
