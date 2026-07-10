<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit\Setup;

defined('ABSPATH') || exit;

/**
 * Pure state machine for the approved nine-step setup wizard (spec 068 FR-134). It projects the
 * real completion + blocker facts into an ordered step list with per-step status (done, current,
 * blocked, upcoming, skipped), an accurate percentage over the required steps, the resume step,
 * and whether the site is safe to launch. WordPress-free, so it is unit-testable. It never invents
 * progress: only steps the operator actually completed count as done.
 */
final class SetupProgress
{
    /**
     * The nine approved steps in order. Optional steps do not count against the percentage.
     *
     * @var list<array{id:string,optional:bool}>
     */
    private const STEPS = [
        ['id' => 'welcome', 'optional' => false],
        ['id' => 'brand', 'optional' => false],
        ['id' => 'kit', 'optional' => false],
        ['id' => 'demo', 'optional' => true],
        ['id' => 'plan', 'optional' => false],
        ['id' => 'backup', 'optional' => false],
        ['id' => 'apply', 'optional' => false],
        ['id' => 'launch', 'optional' => false],
        ['id' => 'done', 'optional' => false],
    ];

    /**
     * @param list<string>          $completed step ids the operator has finished
     * @param array<string,string>  $blockers  step id => human-readable blocker reason
     * @param list<string>          $skipped   optional step ids the operator chose to skip
     *
     * @return array{
     *   steps: list<array{id:string,label:string,optional:bool,status:string,blocker:string}>,
     *   current: string,
     *   percentage: int,
     *   blocked: bool,
     *   canLaunch: bool
     * }
     */
    public function state(array $completed, array $blockers = [], array $skipped = []): array
    {
        $completedSet = array_flip($completed);
        $skippedSet   = array_flip($skipped);

        $steps   = [];
        $current = null;

        foreach (self::STEPS as $step) {
            $id = $step['id'];

            if (isset($completedSet[$id])) {
                $status = 'done';
            } elseif (isset($skippedSet[$id]) && $step['optional']) {
                $status = 'skipped';
            } elseif (isset($blockers[$id])) {
                $status = 'blocked';
            } elseif ($current === null) {
                $status  = 'current';
                $current = $id;
            } else {
                $status = 'upcoming';
            }

            $steps[] = [
                'id'       => $id,
                'label'    => $this->label($id),
                'optional' => $step['optional'],
                'status'   => $status,
                'blocker'  => $blockers[$id] ?? '',
            ];
        }

        return [
            'steps'      => $steps,
            'current'    => $current ?? 'done',
            'percentage' => $this->percentage($completedSet),
            'blocked'    => $blockers !== [],
            'canLaunch'  => $blockers === [] && isset($completedSet['apply']),
        ];
    }

    /**
     * @param array<string,int> $completedSet
     */
    private function percentage(array $completedSet): int
    {
        $required = array_filter(self::STEPS, static fn (array $step): bool => ! $step['optional']);
        $total    = count($required);

        if ($total === 0) {
            return 0;
        }

        $done = count(array_filter($required, static fn (array $step): bool => isset($completedSet[$step['id']])));

        return (int) round($done / $total * 100);
    }

    private function label(string $id): string
    {
        return match ($id) {
            'welcome' => __('Welcome', 'corex'),
            'brand'   => __('Brand', 'corex'),
            'kit'     => __('Choose a kit', 'corex'),
            'demo'    => __('Demo content', 'corex'),
            'plan'    => __('Review plan', 'corex'),
            'backup'  => __('Backup', 'corex'),
            'apply'   => __('Apply', 'corex'),
            'launch'  => __('Launch checklist', 'corex'),
            'done'    => __('Done', 'corex'),
            default   => $id,
        };
    }
}
