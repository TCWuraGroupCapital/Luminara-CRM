<?php

namespace Webkul\Gourwest\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Gourwest\Support\LoadDatasetGenerator;

class LoadDatasetCommand extends Command
{
    protected $signature = 'luminara:load-dataset
                            {--records=5000000 : Number of synthetic customer profiles that must exist}
                            {--purge : Remove the synthetic load dataset instead of generating it}
                            {--force : Allow execution in production environments}';

    protected $description = 'Generate (or purge) a synthetic customer volume so luminara:benchmark measures retrieval at warranty scale.';

    public function handle(LoadDatasetGenerator $generator): int
    {
        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->error('Pass --force to run luminara:load-dataset in production.');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            $this->info(sprintf('Purged %s synthetic load customers.', number_format($generator->purge())));

            return self::SUCCESS;
        }

        $records = (int) $this->option('records');

        if ($records < 1) {
            $this->error('--records must be a positive integer.');

            return self::FAILURE;
        }

        $startedAt = microtime(true);

        $generator->generate($records, function (int $done) use ($records): void {
            if ($done % 500000 === 0 || $done === $records) {
                $this->line(sprintf('  %s / %s', number_format($done), number_format($records)));
            }
        });

        $this->info(sprintf(
            'Synthetic load dataset ready: %s customer profiles in %.1fs.',
            number_format($records),
            microtime(true) - $startedAt
        ));

        return self::SUCCESS;
    }
}
