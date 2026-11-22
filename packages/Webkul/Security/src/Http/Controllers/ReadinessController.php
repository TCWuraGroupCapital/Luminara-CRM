<?php

namespace Webkul\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Webkul\Security\Support\ReadinessProbe;
use Webkul\Security\Support\SecretScrubber;

class ReadinessController
{
    public function __invoke(ReadinessProbe $probe, SecretScrubber $scrubber): JsonResponse
    {
        $report = $scrubber->scrubReport($probe->run());
        $status = ($report['status'] ?? '') === 'fail' ? 503 : 200;

        return response()
            ->json($report, $status, [], JSON_UNESCAPED_SLASHES)
            ->header('Cache-Control', 'no-store');
    }
}
