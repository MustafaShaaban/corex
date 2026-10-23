<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Readiness;

defined('ABSPATH') || exit;

use Corex\Config\Operations\ReadinessSnapshot;

/**
 * The launch checklist as every screen draws it (spec 118): the one readiness snapshot in its two
 * groups, each row with its state, the word and chip for it, what is missing, and a link to the fix
 * only while it is open. The counts are the same wherever they are shown: done over every check
 * that applies (US-OVR-01, US-SEC-09). Pure, so the Overview, Operations, the Dashboard widget and
 * the wizard's route cannot draw it two ways.
 */
final class LaunchChecklist
{
    private const GROUP_ORDER = [ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::GROUP_SITE];

    /**
     * @return array{
     *   groups: list<array{key:string,title:string,done:int,total:int,count:string,rows:list<array<string,mixed>>}>,
     *   done: int,
     *   total: int,
     *   blocking: int,
     *   count: string,
     *   blockingText: string
     * }
     */
    public function build(ReadinessSnapshot $snapshot, string $voice): array
    {
        $rows = array_map(fn (array $check): array => $this->row($check, $voice), $snapshot->checks());

        $groups = [];
        foreach (self::GROUP_ORDER as $key) {
            $members = array_values(array_filter($rows, static fn (array $row): bool => $row['group'] === $key));
            if ($members === []) {
                continue;
            }

            [$done, $total] = $this->tally($members);
            $groups[] = [
                'key'   => $key,
                'title' => ReadinessWords::group($key),
                'done'  => $done,
                'total' => $total,
                'count' => ReadinessWords::count($done, $total, $voice),
                'rows'  => $members,
            ];
        }

        [$done, $total] = $this->tally($rows);
        $blocking       = count($snapshot->blockingKeys());

        return [
            'groups'       => $groups,
            'done'         => $done,
            'total'        => $total,
            'blocking'     => $blocking,
            'count'        => ReadinessWords::count($done, $total, $voice),
            'blockingText' => ReadinessWords::blocking($blocking),
        ];
    }

    /**
     * @param array{key:string,group:string,label:string,state:string,summary:string,resolution_url:string} $check
     *
     * @return array{key:string,group:string,label:string,state:string,chip:string,word:string,note:string,href:string,blocks:bool}
     */
    private function row(array $check, string $voice): array
    {
        $open = $check['state'] !== ReadinessSnapshot::PASS;

        return [
            'key'    => $check['key'],
            'group'  => $check['group'],
            'label'  => $check['label'],
            'state'  => $check['state'],
            'chip'   => ReadinessWords::chip($check['state']),
            'word'   => ReadinessWords::state($check['state'], $voice),
            'note'   => $check['summary'],
            // A done row is not a link (US-OVR-02): there is nothing to fix.
            'href'   => $open ? $check['resolution_url'] : '',
            'blocks' => $check['state'] === ReadinessSnapshot::BLOCKING,
        ];
    }

    /**
     * Done and counted: a check that does not apply to the site is in neither number.
     *
     * @param list<array{state:string}> $rows
     *
     * @return array{0:int,1:int}
     */
    private function tally(array $rows): array
    {
        $counted = array_filter($rows, static fn (array $row): bool => $row['state'] !== ReadinessSnapshot::NOT_APPLICABLE);
        $done    = array_filter($counted, static fn (array $row): bool => $row['state'] === ReadinessSnapshot::PASS);

        return [count($done), count($counted)];
    }
}
