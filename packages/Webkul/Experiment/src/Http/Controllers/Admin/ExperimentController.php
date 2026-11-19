<?php

namespace Webkul\Experiment\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Experiment\ExperimentManager;
use Webkul\Experiment\Http\Requests\ExperimentRequest;
use Webkul\Experiment\Models\Experiment;
use Webkul\Experiment\Repositories\ExperimentRepository;
use Webkul\Experiment\Support\LeadPropensityScorer;
use Webkul\Lead\Repositories\LeadRepository;

class ExperimentController extends Controller
{
    public function __construct(
        protected ExperimentRepository $experimentRepository,
        protected ExperimentManager $experimentManager,
        protected LeadRepository $leadRepository,
        protected LeadPropensityScorer $propensityScorer
    ) {}

    /**
     * Render the Admin A/B Experiments console or return JSON list with batch-computed stats.
     */
    public function index(): View|JsonResponse
    {
        if (request()->expectsJson() || request()->ajax()) {
            /** @var Collection<int, Experiment> $allExperiments */
            $allExperiments = $this->experimentRepository->orderBy('id', 'desc')->all();
            $authorized = $this->filterAuthorizedRecords($allExperiments);
            $batchStats = $this->experimentManager->statsForMany($authorized);

            return new JsonResponse([
                'data' => $authorized->map(
                    fn (Experiment $exp): array => $this->serializeExperiment($exp, $batchStats[$exp->id] ?? null)
                )->values(),
            ]);
        }

        return view('experiment::admin.index');
    }

    /**
     * Return detailed statistical report for a single experiment.
     */
    public function stats(int $id): JsonResponse
    {
        /** @var Experiment $experiment */
        $experiment = $this->experimentRepository->findOrFail($id);

        $this->preventUnauthorizedAccess($experiment->user_id);

        return new JsonResponse([
            'data' => $this->serializeExperiment($experiment),
        ]);
    }

    /**
     * Compute Empirical-Bayes win propensity and expected value for an authorized Lead.
     */
    public function leadPropensity(int $leadId): JsonResponse
    {
        abort_unless(bouncer()->hasPermission('leads.view'), 401, trans('admin::app.errors.unauthorized'));

        $lead = $this->leadRepository->findOrFail($leadId);
        $this->preventUnauthorizedAccess($lead->user_id);

        return new JsonResponse([
            'data' => $this->propensityScorer->score($lead),
        ]);
    }

    /**
     * Store a newly created experiment.
     */
    public function store(ExperimentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $status = (string) $validated['status'];

        Event::dispatch('settings.experiment.create.before');

        /** @var Experiment $experiment */
        $experiment = $this->experimentRepository->create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $status,
            'traffic_allocation' => (int) $validated['traffic_allocation'],
            'variants' => $this->normalizeVariants($validated['variants']),
            'winner_variant_key' => $validated['winner_variant_key'] ?? null,
            'salt' => Str::random(16),
            'user_id' => auth()->guard('user')->id(),
            'started_at' => $status === Experiment::STATUS_RUNNING ? now() : null,
            'ended_at' => $status === Experiment::STATUS_CONCLUDED ? now() : null,
        ]);

        Event::dispatch('settings.experiment.create.after', $experiment);

        return new JsonResponse([
            'data' => $this->serializeExperiment($experiment),
            'message' => trans('experiment::app.index.create-success'),
        ], 201);
    }

    /**
     * Update an existing experiment.
     */
    public function update(ExperimentRequest $request, int $id): JsonResponse
    {
        /** @var Experiment $existing */
        $existing = $this->experimentRepository->findOrFail($id);

        $this->preventUnauthorizedAccess($existing->user_id);

        $validated = $request->validated();
        $status = (string) $validated['status'];

        Event::dispatch('settings.experiment.update.before', $id);

        /** @var Experiment $experiment */
        $experiment = $this->experimentRepository->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $status,
            'traffic_allocation' => (int) $validated['traffic_allocation'],
            'variants' => $this->normalizeVariants($validated['variants']),
            'winner_variant_key' => array_key_exists('winner_variant_key', $validated)
                ? $validated['winner_variant_key']
                : $existing->winner_variant_key,
            'started_at' => $existing->started_at ?? ($status === Experiment::STATUS_RUNNING ? now() : null),
            'ended_at' => $status === Experiment::STATUS_CONCLUDED ? ($existing->ended_at ?? now()) : null,
        ], $id);

        Event::dispatch('settings.experiment.update.after', $experiment);

        return new JsonResponse([
            'data' => $this->serializeExperiment($experiment),
            'message' => trans('experiment::app.index.update-success'),
        ]);
    }

    /**
     * Delete an experiment and its associated event telemetry.
     */
    public function destroy(int $id): JsonResponse
    {
        /** @var Experiment $existing */
        $existing = $this->experimentRepository->findOrFail($id);

        $this->preventUnauthorizedAccess($existing->user_id);

        Event::dispatch('settings.experiment.delete.before', $id);

        $this->experimentRepository->delete($id);

        Event::dispatch('settings.experiment.delete.after', $id);

        return new JsonResponse([
            'message' => trans('experiment::app.index.delete-success'),
        ]);
    }

    /**
     * Format an Experiment model with live statistical telemetry.
     *
     * @param  array<string, mixed>|null  $precomputedStats
     * @return array<string, mixed>
     */
    protected function serializeExperiment(Experiment $experiment, ?array $precomputedStats = null): array
    {
        return [
            'id' => $experiment->id,
            'code' => $experiment->code,
            'name' => $experiment->name,
            'description' => $experiment->description,
            'status' => $experiment->status,
            'traffic_allocation' => $experiment->traffic_allocation,
            'variants' => $experiment->variants,
            'winner_variant_key' => $experiment->winner_variant_key,
            'started_at' => $experiment->started_at?->toIso8601String(),
            'ended_at' => $experiment->ended_at?->toIso8601String(),
            'created_at' => $experiment->created_at?->toIso8601String(),
            'stats' => $precomputedStats ?? $this->experimentManager->statsFor($experiment),
        ];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $variants
     * @return array<int, array{key: string, name: string, weight: int, payload?: array<string, mixed>|null}>
     */
    private function normalizeVariants(array $variants): array
    {
        return array_values(array_map(static function (array $variant): array {
            $normalized = [
                'key' => (string) $variant['key'],
                'name' => (string) $variant['name'],
                'weight' => (int) $variant['weight'],
            ];

            if (array_key_exists('payload', $variant) && $variant['payload'] !== null) {
                $normalized['payload'] = $variant['payload'];
            }

            return $normalized;
        }, $variants));
    }
}
