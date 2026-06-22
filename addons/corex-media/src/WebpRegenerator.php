<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

/**
 * Plans WebP regeneration for existing uploads (spec 061): given a list of attachment items it
 * decides, per item, whether to convert, skip (already has a WebP sibling), or skip (unsupported
 * type / conversion disabled). Pure — no WordPress, no filesystem writes — so it is fully unit-
 * tested; the {@see MediaCommand} supplies the items and performs the actual conversions.
 *
 * Each input item: array{id:int, path:string, mime:string, webp_exists:bool}.
 * Each planned action: array{id:int, path:string, mime:string, action:'convert'|'skip-exists'|'skip-unsupported'}.
 */
final class WebpRegenerator
{
    public function __construct(
        private readonly ImageCapability $capability,
        private readonly MediaSettings $settings,
    ) {
    }

    /**
     * @param list<array{id:int,path:string,mime:string,webp_exists:bool}> $items
     *
     * @return array{actions:list<array{id:int,path:string,mime:string,action:string}>,counts:array{convert:int,skipped:int,total:int}}
     */
    public function plan(array $items): array
    {
        $actions = [];
        $convert = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $plan = ConversionPlan::for((string) $item['path'], (string) $item['mime'], $this->capability, $this->settings);

            if (! $plan->convert) {
                $action = 'skip-unsupported';
            } elseif (! empty($item['webp_exists'])) {
                // Never overwrite an existing sibling during a backfill — originals/derivatives are preserved.
                $action = 'skip-exists';
            } else {
                $action = 'convert';
            }

            $action === 'convert' ? $convert++ : $skipped++;

            $actions[] = [
                'id'     => (int) $item['id'],
                'path'   => (string) $item['path'],
                'mime'   => (string) $item['mime'],
                'action' => $action,
            ];
        }

        return [
            'actions' => $actions,
            'counts'  => ['convert' => $convert, 'skipped' => $skipped, 'total' => count($items)],
        ];
    }
}
