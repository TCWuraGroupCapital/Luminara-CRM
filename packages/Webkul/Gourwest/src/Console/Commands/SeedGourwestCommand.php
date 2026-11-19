<?php

namespace Webkul\Gourwest\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Gourwest\Support\GourwestDemoSeeder;

class SeedGourwestCommand extends Command
{
    protected $signature = 'luminara:seed-gourwest
                            {--force : Allow execution in production environments}';

    protected $description = 'Idempotently seed Gourwest 7-country customers, 11 Brief stakeholders, 8 business system integrations, regional SLA tickets, and loyalty experiment.';

    public function handle(GourwestDemoSeeder $seeder): int
    {
        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->error('Pass --force to run luminara:seed-gourwest in production.');

            return self::FAILURE;
        }

        $summary = $seeder->seed();

        $this->info('Gourwest global CRM dataset seeded cleanly.');
        $this->table(
            ['Metric', 'Count / Status'],
            [
                ['Regional Operational Groups', (string) $summary['groups_seeded']],
                ['Brief Stakeholders Provisioned', (string) $summary['staff_seeded']],
                ['Enterprise Integration Contracts', (string) $summary['integrations_seeded']],
                ['Multi-Country Customer Profiles', (string) $summary['customers_seeded']],
                ['Cross-Region Duplicate Pairs Ready', (string) $summary['duplicate_pairs_ready']],
                ['Regional SLA Service Tickets', (string) $summary['tickets_seeded']],
                ['Loyalty Onboarding A/B Experiment', $summary['experiment_seeded'] ? 'Active' : 'Skipped'],
            ]
        );

        return self::SUCCESS;
    }
}
