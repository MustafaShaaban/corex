<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

use Corex\Provisioning\ApplyOutcome;
use Corex\Provisioning\ApplyPreview;

defined('ABSPATH') || exit;

/**
 * Pure reshaping of the kit-activation {@see ApplyPreview} / {@see ApplyOutcome} into render-ready arrays for
 * the prompt and the "what changed" summary (spec 042). It emits machine actions/reasons and counts only — the
 * screen maps those to translated, escaped labels — so this stays WordPress-free and unit-testable.
 */
final class KitActivationView
{
    /**
     * @return array{
     *   kit:string,
     *   rows:list<array{title:string,slug:string,action:string,reason:string}>,
     *   front:?string,
     *   modules:list<string>
     * }
     */
    public function prompt(ApplyPreview $preview): array
    {
        $rows = [];

        foreach ($preview->pages as $disposition) {
            $rows[] = [
                'title'  => $disposition->title,
                'slug'   => $disposition->slug,
                'action' => $disposition->action,
                'reason' => $disposition->reason,
            ];
        }

        return [
            'kit'     => $preview->kit,
            'rows'    => $rows,
            'front'   => $preview->frontTargetSlug,
            'modules' => $preview->modules,
        ];
    }

    /**
     * @return array{
     *   created:int,
     *   populated:int,
     *   skipped:int,
     *   frontPageId:?int,
     *   rows:list<array{title:string,slug:string,action:string,pageId:?int,isFront:bool}>
     * }
     */
    public function summary(ApplyOutcome $outcome): array
    {
        $rows = [];

        foreach ($outcome->pages() as $page) {
            $disposition = $page['disposition'];
            $rows[]      = [
                'title'   => $disposition->title,
                'slug'    => $disposition->slug,
                'action'  => $disposition->action,
                'pageId'  => $page['pageId'],
                'isFront' => $page['isFront'],
            ];
        }

        return [
            'created'     => count($outcome->created()),
            'populated'   => count($outcome->populated()),
            'skipped'     => count($outcome->skipped()),
            'frontPageId' => $outcome->frontPageId,
            'rows'        => $rows,
        ];
    }
}
