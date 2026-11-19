<?php

use Webkul\Experiment\ExperimentManager;

if (! function_exists('experiment')) {
    function experiment(?string $code = null, ?string $subjectId = null): ExperimentManager|string|null
    {
        /** @var ExperimentManager $manager */
        $manager = app(ExperimentManager::class);

        if ($code === null) {
            return $manager;
        }

        return $manager->variant($code, $subjectId);
    }
}
