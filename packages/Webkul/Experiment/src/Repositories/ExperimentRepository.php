<?php

namespace Webkul\Experiment\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Experiment\Contracts\Experiment;

class ExperimentRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return Experiment::class;
    }
}
