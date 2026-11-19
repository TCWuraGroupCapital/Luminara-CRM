<?php

namespace Webkul\Ledger\Support;

use Webkul\Ledger\Contracts\AnchorDriver;
use Webkul\Ledger\Models\LedgerEpoch;

class LocalSignedAnchorDriver implements AnchorDriver
{
    public function __construct(private readonly LedgerSigner $signer) {}

    public function name(): string
    {
        return 'local';
    }

    public function anchor(LedgerEpoch $epoch): ?string
    {
        $digest = hash('sha256', implode('|', [
            'luminara-local-anchor-v1',
            $epoch->id,
            $epoch->start_height,
            $epoch->end_height,
            $epoch->merkle_root,
            $epoch->prev_epoch_root,
        ]));

        $signed = $this->signer->signHash($digest);

        return 'local:ed25519:'.$signed['proof'];
    }
}
