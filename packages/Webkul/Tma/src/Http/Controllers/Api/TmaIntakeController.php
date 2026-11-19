<?php

namespace Webkul\Tma\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Agent\Support\ActionExecutor;
use Webkul\Agent\Support\ActionOutcome;
use Webkul\Agent\Support\ActionRegistry;
use Webkul\Tma\Support\IntakeParamMapper;

class TmaIntakeController extends Controller
{
    public function store(Request $request, ActionExecutor $executor, ActionRegistry $registry): JsonResponse
    {
        $rawText = $request->input('text') ?? $request->input('note') ?? $request->input('message');
        $text = is_string($rawText) ? trim($rawText) : '';

        if ($text === '' || mb_strlen($text, 'UTF-8') > 4000) {
            $error = ActionOutcome::error(
                422,
                'invalid_params',
                'Provide a non-empty capture text up to 4,000 characters.'
            );

            return new JsonResponse($error->body, $error->httpStatus);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey !== null && strlen($idempotencyKey) > 128) {
            $error = ActionOutcome::error(
                422,
                'invalid_idempotency_key',
                'The Idempotency-Key header exceeds 128 characters.'
            );

            return new JsonResponse($error->body, $error->httpStatus);
        }

        $payload = $request->json()->all();
        $dryRun = (bool) ($payload['dry_run'] ?? false);
        unset($payload['dry_run']);

        $params = IntakeParamMapper::build($registry, $text, 'tma', $payload);
        $outcome = $executor->execute('intake_capture', $params, $idempotencyKey, $dryRun);

        return new JsonResponse($outcome->body, $outcome->httpStatus);
    }
}
