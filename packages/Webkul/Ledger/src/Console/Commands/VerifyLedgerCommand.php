<?php

namespace Webkul\Ledger\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Ledger\Support\LedgerVerifier;

class VerifyLedgerCommand extends Command
{
    protected $signature = 'luminara:ledger-verify
        {--limit= : Maximum number of recent blocks to verify}
        {--json : Output verification report as JSON}';

    protected $description = 'Cryptographically verify the append-only Data Block chain and sealed Merkle Epochs';

    public function handle(LedgerVerifier $verifier): int
    {
        $rawLimit = $this->option('limit');
        $limit = ($rawLimit !== null && $rawLimit !== '') ? max(1, (int) $rawLimit) : null;

        $report = $verifier->verifyChain($limit);

        if ((bool) $this->option('json')) {
            $encoded = json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->line($encoded);

            return $report['ok'] ? self::SUCCESS : self::FAILURE;
        }

        if ($report['ok']) {
            $this->info(sprintf(
                'Ledger verified: %d blocks, %d epochs, head #%d (%s).',
                $report['checked_blocks'],
                $report['checked_epochs'],
                $report['head_height'],
                $report['head_hash'],
            ));

            return self::SUCCESS;
        }

        $this->error('Ledger tamper or discontinuity detected:');

        foreach ($report['errors'] as $error) {
            $this->line(' - '.$error);
        }

        return self::FAILURE;
    }
}
