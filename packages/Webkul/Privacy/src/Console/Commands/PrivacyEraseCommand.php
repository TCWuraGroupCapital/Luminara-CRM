<?php

namespace Webkul\Privacy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Webkul\Privacy\Support\PersonEraser;

class PrivacyEraseCommand extends Command
{
    protected $signature = 'luminara:privacy:erase
                            {person : Person id}
                            {--confirm= : The person\'s current name, typed exactly, to confirm}';

    protected $description = 'Erase one person\'s personal data on request; deals and quotes are kept. Cannot be undone.';

    public function handle(PersonEraser $eraser): int
    {
        $id = (int) $this->argument('person');
        $name = DB::table('persons')->where('id', $id)->value('name');

        if (! is_string($name)) {
            $this->error("Person #{$id} does not exist.");

            return self::FAILURE;
        }

        if (trim((string) $this->option('confirm')) !== trim($name)) {
            $this->error('Repeat with --confirm="<the person\'s current name>" to erase them. This cannot be undone.');

            return self::FAILURE;
        }

        try {
            $summary = $eraser->erase($id);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Person #{$id} erased.");
        $this->table(['What', 'Count'], collect($summary)->map(fn ($value, $key) => [$key, (string) $value])->values()->all());

        return self::SUCCESS;
    }
}
