<?php

/**
 * Provider boot diagnostics across the real network (spec 100 T071).
 *
 * @package Corex\Tests\Integration\Multisite
 */

declare(strict_types=1);

$bootErrors = <<<'PHP'
$messages = \Corex\Boot::app()->container()->make(\Corex\Support\BootLogger::class)->messages();
$errors = array_values(array_filter($messages, static fn (array $message): bool => $message['level'] === 'error'));
echo 'COREX_MS_JSON:' . wp_json_encode(['errors' => $errors]);
PHP;

it('records no provider boot error on any fixture site', function () use ($bootErrors) {
    $siteIds = [
        1,
        $this->siteIdForPath('/site2/'),
        $this->siteIdForPath('/site3/'),
    ];

    foreach ($siteIds as $siteId) {
        $state = $this->wpCliJson($siteId, $bootErrors);
        expect($state['errors'])->toBe([], 'Boot errors on site ' . $siteId);
    }
});
