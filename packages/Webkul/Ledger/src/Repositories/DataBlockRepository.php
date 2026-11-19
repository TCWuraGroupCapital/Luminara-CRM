<?php

namespace Webkul\Ledger\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Ledger\Contracts\DataBlock;

class DataBlockRepository extends Repository
{
    public function model(): string
    {
        return DataBlock::class;
    }
}
