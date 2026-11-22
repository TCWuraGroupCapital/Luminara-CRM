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

        // The production image also sets expose_php=Off. Remove it here so a
        // public probe does not advertise the PHP version if that ini is left on.
        header_remove('X-Powered-By');

        return response()
            ->json($report, $status, [], JSON_UNESCAPED_SLASHES)
            ->header('Cache-Control', 'no-store');
    }
}
