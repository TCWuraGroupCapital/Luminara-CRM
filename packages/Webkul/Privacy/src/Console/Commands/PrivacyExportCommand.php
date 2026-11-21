<?php

namespace Webkul\Privacy\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Privacy\Support\PersonDataExporter;
use Webkul\Security\Support\SecurityAuditRecorder;

class PrivacyExportCommand extends Command
{
    protected $signature = 'luminara:privacy:export
                            {person : Person id}
                            {--output= : File to write; prints to the console when omitted}';

    protected $description = 'Export everything the CRM holds about one person as JSON (access and portability requests).';

    public function handle(PersonDataExporter $exporter): int
    {
        $id = (int) $this->argument('person');
        $data = $exporter->export($id);

        if ($data === null) {
            $this->error("Person #{$id} does not exist.");

            return self::FAILURE;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $output = (string) $this->option('output');

        SecurityAuditRecorder::record('privacy.person_exported', 'person', (string) $id, ['via' => 'console']);

        if ($output === '') {
            $this->line($json);

            return self::SUCCESS;
        }

        file_put_contents($output, $json);
        $this->info("Wrote {$output}.");

        return self::SUCCESS;
    }
}
