<?php

/**
 * What a provider's "Test connection" makes of an answer (spec 119, US-SET-03), from answers the
 * providers give.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\Providers\CloudflareTokenProbe;
use Corex\Config\Settings\Providers\PageSpeedKeyProbe;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_json_encode')->alias(static fn (mixed $value): string => (string) json_encode($value));
});

it('reads Google\'s answers to a PageSpeed key', function (int $status, ?array $body, bool $worked, string $reason) {
    $outcome = PageSpeedKeyProbe::classify($status, $body);

    expect($outcome->worked)->toBe($worked)
        ->and($outcome->reason)->toBe($reason);
})->with([
    // What Google answers a key it does not know (read from the live API on 2026-10-23).
    'a key Google refuses' => [400, ['error' => [
        'code'    => 400,
        'message' => 'API key not valid. Please pass a valid API key.',
        'details' => [['reason' => 'API_KEY_INVALID']],
    ]], false, 'Google refused the key'],
    'a key accepted, and the missing page named' => [400, ['error' => [
        'code'    => 400,
        'message' => "Invalid value at 'url' (TYPE_STRING)",
    ]], true, ''],
    'a key over its quota' => [429, ['error' => ['message' => 'Quota exceeded for quota metric']], false, 'the key\'s quota is used up for today'],
    'a key not allowed this API' => [403, ['error' => ['status' => 'PERMISSION_DENIED']], false, 'the key may not use the PageSpeed Insights API'],
    'a server error' => [503, null, false, 'Google answered with HTTP 503'],
]);

it('reads Cloudflare\'s answers to a token', function (?array $body, bool $worked, string $reason) {
    $outcome = CloudflareTokenProbe::classify($body);

    expect($outcome->worked)->toBe($worked)
        ->and($outcome->reason)->toBe($reason);
})->with([
    'an active token' => [['success' => true, 'result' => ['status' => 'active']], true, ''],
    'a disabled token' => [['success' => true, 'result' => ['status' => 'disabled']], false, 'Cloudflare says the token is disabled'],
    'a token Cloudflare does not know' => [['success' => false, 'errors' => [['code' => 1000, 'message' => 'Invalid API Token']]], false, 'Cloudflare refused the token'],
    'an answer that is not JSON' => [null, false, 'Cloudflare gave an answer CoreX cannot read'],
]);
