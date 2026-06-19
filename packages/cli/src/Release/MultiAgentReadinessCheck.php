<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Evaluates branch, ownership, and handoff evidence for multi-agent work.
 */
final class MultiAgentReadinessCheck
{
    /**
     * @param list<AgentWorkUnit> $workUnits
     */
    public function evaluate(array $workUnits): ReadinessFinding
    {
        $evidence = array_merge(
            $this->branchEvidence($workUnits),
            $this->overlapEvidence($workUnits),
            $this->completionEvidence($workUnits),
        );

        if ($evidence === []) {
            return new ReadinessFinding(
                'multi-agent',
                ReadinessFinding::STATUS_PASS,
                'Multi-agent work units are branch-isolated, file-owned, and evidenced.',
                ['multi-agent:clean'],
                'docs',
                false,
                'None',
            );
        }

        return new ReadinessFinding(
            'multi-agent',
            ReadinessFinding::STATUS_FAIL,
            'Multi-agent work units have blocking coordination issues.',
            $evidence,
            'docs',
            true,
            'Move work off main, split overlapping file ownership, and attach guard evidence before completion.',
        );
    }

    /**
     * @param list<AgentWorkUnit> $workUnits
     *
     * @return list<string>
     */
    private function branchEvidence(array $workUnits): array
    {
        $evidence = [];

        foreach ($workUnits as $workUnit) {
            if ($workUnit->branch === 'main') {
                $evidence[] = sprintf('branch:main:%s', $workUnit->taskLabel());
            }
        }

        return $evidence;
    }

    /**
     * @param list<AgentWorkUnit> $workUnits
     *
     * @return list<string>
     */
    private function overlapEvidence(array $workUnits): array
    {
        $ownersByFile = [];

        foreach ($workUnits as $workUnit) {
            foreach ($workUnit->filesOwned as $fileOwned) {
                $ownersByFile[$fileOwned][] = $workUnit->taskLabel();
            }
        }

        $evidence = [];

        foreach ($ownersByFile as $fileOwned => $owners) {
            $owners = array_values(array_unique($owners));

            if (count($owners) > 1) {
                $evidence[] = sprintf('overlap:%s:%s', $fileOwned, implode(',', $owners));
            }
        }

        return $evidence;
    }

    /**
     * @param list<AgentWorkUnit> $workUnits
     *
     * @return list<string>
     */
    private function completionEvidence(array $workUnits): array
    {
        $evidence = [];

        foreach ($workUnits as $workUnit) {
            foreach ($workUnit->completionIssues() as $issue) {
                $evidence[] = sprintf('completion:%s:%s', $workUnit->taskLabel(), $issue);
            }
        }

        return $evidence;
    }
}

