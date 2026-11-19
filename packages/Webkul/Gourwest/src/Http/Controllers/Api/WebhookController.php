<?php

namespace Webkul\Gourwest\Http\Controllers\Api;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Gourwest\Support\IntegrationSyncService;
use Webkul\Security\Support\SecurityAuditRecorder;

class WebhookController extends Controller
{
    public function __construct(
        protected IntegrationSyncService $syncService
    ) {}

    public function ingest(Request $request, string $systemCode): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        $signatureHeader = $request->header('X-Gourwest-Signature');
        $signature = is_string($signatureHeader) ? $signatureHeader : null;

        if (! $this->syncService->verifySignature($rawBody, $signature)) {
            SecurityAuditRecorder::record(
                event: 'gourwest.webhook.rejected',
                auditableType: 'gourwest_integration',
                auditableId: strtolower(trim($systemCode)),
                metadata: [
                    'reason' => $signature === null ? 'missing_signature' : 'invalid_signature',
                    'system_code' => strtolower(trim($systemCode)),
                ]
            );

            return new JsonResponse([
                'message' => 'Invalid or missing X-Gourwest-Signature HMAC-SHA256 header.',
            ], 401);
        }

        $decoded = json_decode($rawBody, true);
        $payload = is_array($decoded) ? $decoded : $request->all();

        try {
            $result = $this->syncService->ingestWebhook($systemCode, $payload, $rawBody);
        } catch (ModelNotFoundException) {
            return new JsonResponse([
                'message' => 'Unsupported Gourwest integration system code.',
            ], 404);
        }

        return new JsonResponse([
            'status' => 'processed',
            'data' => [
                'system_code' => $result['integration']->system_code,
                'event_id' => $result['event']->id,
                'external_ref' => $result['event']->external_ref,
                'latency_ms' => $result['event']->latency_ms,
                'customer_updated' => $result['customer_updated'],
                'duplicate' => $result['duplicate'],
                // Only the caller that just credited this member sees the balance; there is no lookup by member number.
                'points_balance' => $result['member']['points_balance'] ?? null,
                'tier' => $result['member']['tier'] ?? null,
            ],
        ]);
    }
}
