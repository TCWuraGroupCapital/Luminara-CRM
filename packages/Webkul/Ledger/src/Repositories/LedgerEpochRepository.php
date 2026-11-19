<?php

namespace Webkul\Ledger\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Ledger\Contracts\LedgerEpoch;

class LedgerEpochRepository extends Repository
{
    public function model(): string
    {
        return LedgerEpoch::class;
    }
}
