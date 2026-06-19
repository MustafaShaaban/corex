<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

use Corex\Cli\Release\CiSecurityReadiness;
use Corex\Cli\Release\ComponentCoverageReadinessCheck;
use Corex\Cli\Release\DeploymentReadinessCheck;
use Corex\Cli\Release\FreeProBoundaryReadinessCheck;
use Corex\Cli\Release\MetadataConsistencyCheck;
use Corex\Cli\Release\MultiAgentReadinessCheck;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldValidator;
use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Typed dependency bundle for the readiness command.
 */
final class ReadinessCommandServices
{
    public readonly MetadataConsistencyCheck $metadata;

    public readonly CiSecurityReadiness $ciSecurity;

    public readonly string $root;

    public readonly SiteScaffolder $siteScaffolder;

    public readonly SiteScaffoldValidator $siteScaffoldValidator;

    public readonly DeploymentReadinessCheck $deploymentReadiness;

    public readonly ComponentCoverageReadinessCheck $componentCoverage;

    public readonly FreeProBoundaryReadinessCheck $freeProBoundary;

    public readonly MultiAgentReadinessCheck $multiAgent;

    /**
     * @param array{
     *     metadata: MetadataConsistencyCheck,
     *     ciSecurity: CiSecurityReadiness,
     *     root: string,
     *     siteScaffolder: SiteScaffolder,
     *     siteScaffoldValidator: SiteScaffoldValidator,
     *     deploymentReadiness: DeploymentReadinessCheck,
     *     componentCoverage: ComponentCoverageReadinessCheck,
     *     freeProBoundary: FreeProBoundaryReadinessCheck,
     *     multiAgent: MultiAgentReadinessCheck
     * } $services
     */
    private function __construct(array $services)
    {
        $this->metadata = self::requiredInstance($services, 'metadata', MetadataConsistencyCheck::class);
        $this->ciSecurity = self::requiredInstance($services, 'ciSecurity', CiSecurityReadiness::class);
        $this->root = self::requiredRoot($services);
        $this->siteScaffolder = self::requiredInstance($services, 'siteScaffolder', SiteScaffolder::class);
        $this->siteScaffoldValidator = self::requiredInstance($services, 'siteScaffoldValidator', SiteScaffoldValidator::class);
        $this->deploymentReadiness = self::requiredInstance($services, 'deploymentReadiness', DeploymentReadinessCheck::class);
        $this->componentCoverage = self::requiredInstance($services, 'componentCoverage', ComponentCoverageReadinessCheck::class);
        $this->freeProBoundary = self::requiredInstance($services, 'freeProBoundary', FreeProBoundaryReadinessCheck::class);
        $this->multiAgent = self::requiredInstance($services, 'multiAgent', MultiAgentReadinessCheck::class);
    }

    /**
     * @param array{
     *     metadata: MetadataConsistencyCheck,
     *     ciSecurity: CiSecurityReadiness,
     *     root: string,
     *     siteScaffolder: SiteScaffolder,
     *     siteScaffoldValidator: SiteScaffoldValidator,
     *     deploymentReadiness: DeploymentReadinessCheck,
     *     componentCoverage: ComponentCoverageReadinessCheck,
     *     freeProBoundary: FreeProBoundaryReadinessCheck,
     *     multiAgent: MultiAgentReadinessCheck
     * } $services
     */
    public static function fromArray(array $services): self
    {
        return new self($services);
    }

    /**
     * @template T of object
     *
     * @param array<string,mixed> $services
     * @param class-string<T>     $class
     *
     * @return T
     */
    private static function requiredInstance(array $services, string $key, string $class): object
    {
        $service = $services[$key] ?? null;

        if (! $service instanceof $class) {
            throw new InvalidArgumentException(sprintf('Readiness command service "%s" must be an instance of %s.', $key, $class));
        }

        return $service;
    }

    /**
     * @param array<string,mixed> $services
     */
    private static function requiredRoot(array $services): string
    {
        $root = $services['root'] ?? null;

        if (! is_string($root) || trim($root) === '') {
            throw new InvalidArgumentException('Readiness command service "root" is required.');
        }

        return rtrim(str_replace('\\', '/', $root), '/');
    }
}
