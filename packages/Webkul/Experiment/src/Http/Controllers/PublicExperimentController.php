<?php

namespace Webkul\Experiment\Http\Controllers;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Experiment\ExperimentManager;
use Webkul\Experiment\Models\Experiment;
use Webkul\Experiment\Repositories\ExperimentRepository;

class PublicExperimentController extends Controller
{
    public function __construct(
        protected ExperimentRepository $experimentRepository,
        protected ExperimentManager $experimentManager
    ) {}

    /**
     * Deterministically assign a visitor to an experiment variant and return a signed subject token.
     */
    public function assign(Request $request, string $code): JsonResponse
    {
        if (! preg_match('/^[a-z0-9_]{1,64}$/', $code)) {
            return new JsonResponse(['message' => 'Not found.'], 404);
        }

        /** @var Experiment|null $experiment */
        $experiment = $this->experimentRepository->findOneByField('code', $code);

        if (! $experiment || $experiment->status === Experiment::STATUS_DRAFT) {
            return new JsonResponse(['message' => 'Not found.'], 404);
        }

        $subjectId = $this->experimentManager->resolveSubjectId($request);
        $variantKey = $this->experimentManager->variant(
            $experiment->code,
            $subjectId,
            ! $this->experimentManager->isBotRequest($request)
        );
        $matchedVariant = collect($experiment->variants)->firstWhere('key', $variantKey);

        return new JsonResponse([
            'experiment' => $experiment->code,
            'status' => $experiment->status,
            'variant' => $variantKey,
            'payload' => is_array($matchedVariant) ? ($matchedVariant['payload'] ?? null) : null,
            'subject_token' => $this->experimentManager->signSubjectToken($subjectId),
        ], 200, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Record a client conversion event for a running experiment using an authenticated subject token or cookie.
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'experiment' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'event_key' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_:\-]+$/'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'metadata' => ['nullable', 'array', 'max:10'],
            'metadata.*' => [
                'nullable',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && ! is_scalar($value)) {
                        $fail("The {$attribute} field must be a scalar value.");

                        return;
                    }

                    if ($value !== null && mb_strlen((string) $value) > 255) {
                        $fail("The {$attribute} field must not exceed 255 characters.");
                    }
                },
            ],
            '_exp_token' => ['nullable', 'string', 'max:512'],
        ]);

        $subjectId = $this->experimentManager->resolveConversionSubjectId($request);

        if ($subjectId === null) {
            return new JsonResponse([
                'recorded' => false,
                'message' => 'Unverified experiment subject.',
            ], 422);
        }

        $recorded = $this->experimentManager->recordConversion(
            experimentCode: (string) $validated['experiment'],
            subjectId: $subjectId,
            eventKey: (string) ($validated['event_key'] ?? 'default'),
            value: isset($validated['value']) ? (float) $validated['value'] : 0.0,
            metadata: $validated['metadata'] ?? null,
        );

        return new JsonResponse([
            'recorded' => $recorded,
        ]);
    }
}
