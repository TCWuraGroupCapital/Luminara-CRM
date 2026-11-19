<?php

namespace Webkul\Experiment\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Experiment\Contracts\ExperimentEvent;

class ExperimentEventRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return ExperimentEvent::class;
    }
}
