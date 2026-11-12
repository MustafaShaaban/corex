<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview\Cards;

defined('ABSPATH') || exit;

use Corex\Config\Data\CategorisedDataSource;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Overview\OverviewCard;
use Corex\Config\Readiness\ChecklistMarkup;
use Corex\Data\DataSourceCapabilities;

/**
 * The Overview's data card (spec 118, US-OVR-08): the data the site collects, not CoreX's own
 * records. Only sources in the records group are listed (form submissions, newsletter subscribers,
 * job applications, and any a site's code or an add-on declares so), each with its count, whether
 * it can be edited, and a link to it on the Data screen. The activity log, jobs, notifications and
 * the other logs stay on the Data screen, which a footnote says. A source whose count fails is
 * listed with "Could not load", never left out (US-OVR-06).
 */
final class DataSourcesCard implements OverviewCard
{
    private const DATA_SCREEN = 'admin.php?page=corex-data-models&tab=records';

    public function __construct(private readonly DataRegistry $data)
    {
    }

    public function key(): string
    {
        return 'data';
    }

    public function title(): string
    {
        return __('Data sources', 'corex');
    }

    public function render(array $settingValues): string
    {
        $rows = '';
        foreach ($this->data->all() as $source) {
            if ($this->isOwnersData($source)) {
                $rows .= $this->row($source);
            }
        }

        $body = $rows === ''
            ? '<p class="corex-overview__muted">' . esc_html__('No data yet. Form submissions, newsletter subscribers and job applications appear here once your site receives them.', 'corex') . '</p>'
            : '<ul class="corex-overview__sources">' . $rows . '</ul>';

        return '<section class="corex-surface corex-overview__card corex-overview__card--compact" data-card="data">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html($this->title()) . '</h2>'
            . '<a class="corex-overview__link" href="' . esc_url(admin_url(self::DATA_SCREEN)) . '">'
            . esc_html__('Open Data', 'corex') . ' &rarr;</a></header>'
            . $body
            . '<p class="corex-overview__muted corex-overview__footnote">'
            . esc_html__('CoreX\'s own records, such as the activity log, are on the Data screen.', 'corex') . '</p></section>';
    }

    /** A source that does not say which group it is in is listed with the records, as on the Data screen. */
    private function isOwnersData(DataSource $source): bool
    {
        return ! $source instanceof CategorisedDataSource || $source->category() === CategorisedDataSource::RECORDS;
    }

    private function row(DataSource $source): string
    {
        $href = add_query_arg(['source' => $source->key()], admin_url(self::DATA_SCREEN));

        try {
            $count = '<span class="corex-overview__src-count">' . esc_html(number_format_i18n($source->total())) . '</span>'
                . '<span class="corex-overview__src-access">' . esc_html($this->access($source)) . '</span>';
        } catch (\Throwable) {
            $count = ChecklistMarkup::chip('unavailable', __('Could not load', 'corex'));
        }

        return '<li><a class="corex-overview__src-name" href="' . esc_url($href) . '">' . esc_html($source->label()) . '</a>'
            . $count . '</li>';
    }

    private function access(DataSource $source): string
    {
        $capabilities = $this->data->capabilities($source->key());
        $editable     = $capabilities !== null
            && ($capabilities->supports(DataSourceCapabilities::UPDATE) || $capabilities->supports(DataSourceCapabilities::CREATE));

        return $editable ? __('Can edit', 'corex') : __('Read only', 'corex');
    }
}
