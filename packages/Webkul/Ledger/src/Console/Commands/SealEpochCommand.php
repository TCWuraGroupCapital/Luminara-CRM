<?php

namespace Webkul\Ledger\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Ledger\Support\LedgerVerifier;

class SealEpochCommand extends Command
{
    protected $signature = 'luminara:ledger-seal {--json : Output epoch seal summary as JSON}';

    protected $description = 'Seal unsealed Data Blocks into a signed RFC 6962 binary Merkle Epoch';

    public function handle(LedgerVerifier $verifier): int
    {
        $epoch = $verifier->sealPendingEpoch();

        if ((bool) $this->option('json')) {
            $payload = $epoch === null
                ? ['sealed' => false, 'epoch' => null]
                : [
                    'sealed' => true,
                    'epoch' => [
                        'id' => $epoch->id,
                        'start_height' => $epoch->start_height,
                        'end_height' => $epoch->end_height,
                        'block_count' => $epoch->block_count,
                        'merkle_root' => $epoch->merkle_root,
                        'prev_epoch_root' => $epoch->prev_epoch_root,
                        'server_signature' => $epoch->server_signature,
                        'anchor_driver' => $epoch->anchor_driver,
                        'anchor_reference' => $epoch->anchor_reference,
                        'sealed_at' => $epoch->sealed_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
                    ],
                ];

            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->line($encoded);

            return self::SUCCESS;
        }

        if ($epoch === null) {
            $this->info('No pending unsealed Data Blocks found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Sealed Epoch #%d (blocks %d..%d, count=%d, root=%s, anchor=%s).',
            $epoch->id,
            $epoch->start_height,
            $epoch->end_height,
            $epoch->block_count,
            $epoch->merkle_root,
            $epoch->anchor_reference ?? 'none',
        ));

        return self::SUCCESS;
    }
}
