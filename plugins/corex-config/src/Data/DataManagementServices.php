<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\Data;
defined('ABSPATH') || exit;

use Corex\Config\DataModels\DataExportService;
use Corex\Config\DataModels\DataCsvParser;
use Corex\Config\DataModels\DataImportService;
use Corex\Config\DataModels\DataImportStore;
use Corex\Config\DataModels\ImportReportWriter;
use Corex\Config\DataModels\MigrationService;

/** Explicit application services consumed by the thin Data REST boundary. */
final readonly class DataManagementServices
{
    public function __construct(
        public DataSourceService $sources,
        public DataQueryService $queries,
        public DataMutationService $mutations,
        public DataImportService $imports,
        public DataCsvParser $csv,
        public DataImportStore $importRuns,
        public ImportReportWriter $importReports,
        public DataExportService $exports,
        public MigrationService $migrations,
    ) {
    }
}
