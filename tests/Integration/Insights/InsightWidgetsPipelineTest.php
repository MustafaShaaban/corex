<?php

/**
 * Integration test: the Insights widget pipeline on real ./wp (spec 068: T191). The controller
 * gathers real facts through `InsightWidgetFacts` and composes the designed `InsightWidgets` set —
 * every widget carries an honest state derived from live WordPress + CoreX services.
 *
 * @package Corex\Tests\Integration\Insights
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Insights\InsightsController;

it('composes the seven designed Insights widgets from real gathered facts', function () {
    $controller = Boot::app()->container()->make(InsightsController::class);

    $widgets = $controller->widgetList();
    $keys    = array_column($widgets, 'key');

    expect($keys)->toBe(['performance', 'cloudflare', 'security', 'seo', 'ai', 'ops', 'forms']);

    // Every widget carries a non-empty honest state and a title — never a fabricated blank.
    foreach ($widgets as $widget) {
        expect($widget['state'])->not->toBe('')
            ->and($widget['state'])->not->toBe('planned')
            ->and($widget['title'])->not->toBe('');
    }

    // The Forms & Flows widget projects real live counts (no planned placeholder).
    $forms = array_values(array_filter($widgets, static fn (array $w): bool => $w['key'] === 'forms'))[0];
    expect($forms['state'])->toBe('live')
        ->and($forms['rows'])->not->toBe([]);
});
