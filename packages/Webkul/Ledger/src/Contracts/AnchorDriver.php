<?php

namespace Webkul\Ledger\Contracts;

use Webkul\Ledger\Models\LedgerEpoch;

interface AnchorDriver
{
    public function name(): string;

    public function anchor(LedgerEpoch $epoch): ?string;
}
