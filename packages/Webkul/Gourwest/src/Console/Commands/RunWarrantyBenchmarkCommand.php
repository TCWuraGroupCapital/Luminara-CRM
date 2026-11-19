<?php

namespace Webkul\Gourwest\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Gourwest\Support\WarrantyBenchmarkRunner;

class RunWarrantyBenchmarkCommand extends Command
{
    protected $signature = 'luminara:benchmark
                            {--samples=25 : Number of timed retrieval queries}
                            {--json : Output machine-readable JSON warranty telemetry}';

    protected $description = 'Measure Gourwest unified retrieval latency (p50/p95/p99) against the loaded customer volume, and audit index, MFA, and privacy-regime readiness.';

    public function handle(WarrantyBenchmarkRunner $runner): int
    {
        $report = $runner->run(max(1, (int) $this->option('samples')));

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['passes_3s_sla'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Gourwest Global CRM Service Warranty Benchmark');
        $this->table(
            ['Warranty Control', 'Measured Telemetry', 'Target', 'Status'],
            [
                [
                    'Unified Customer & Loyalty Retrieval (p50 / p95 / p99)',
                    sprintf('%.2f ms / %.2f ms / %.2f ms (n=%d)', $report['p50_ms'], $report['p95_ms'], $report['p99_ms'], $report['samples']),
                    sprintf('<= %.0f ms', $report['sla_target_ms']),
                    $report['passes_3s_sla'] ? 'PASS' : 'FAIL',
                ],
                [
                    'Customer Volume Measured',
                    sprintf('%s profiles loaded; %d / %d core tables indexed', number_format($report['dataset_records']), count(array_filter($report['index_audit'], static fn (array $r): bool => $r['ready'])), count($report['index_audit'])),
                    number_format($report['capacity_target_records']).' records',
                    $report['capacity_verified'] ? 'PASS' : ($report['five_million_ready'] ? 'INDEXED ONLY' : 'WARN'),
                ],
                [
                    'Deep Readiness Probe (single check, not an uptime measurement)',
                    $report['health_probe_ready'] ? 'DB + Cache + Storage healthy' : 'Degraded probe',
                    'Healthy',
                    $report['health_probe_ready'] ? 'PASS' : 'FAIL',
                ],
                [
                    'TOTP MFA & Immutable Security Audit Trail',
                    $report['mfa_ready'] ? 'RFC 6238 TOTP + Append-Only Audit Active' : 'Unavailable',
                    'Mandatory',
                    $report['mfa_ready'] ? 'PASS' : 'FAIL',
                ],
                [
                    'Multi-Jurisdiction Privacy Regimes',
                    sprintf('%d / 7 statutes mapped', $report['privacy_regimes_covered']),
                    '7 countries (AU/NZ/SG/MY/TH/UK/CA)',
                    $report['privacy_regimes_ready'] ? 'PASS' : 'FAIL',
                ],
            ]
        );

        return $report['passes_3s_sla'] ? self::SUCCESS : self::FAILURE;
    }
}
