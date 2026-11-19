<?php

namespace Webkul\Experiment;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Webkul\Experiment\Models\Experiment;
use Webkul\Experiment\Repositories\ExperimentRepository;
use Webkul\Experiment\Support\Bucketer;
use Webkul\Experiment\Support\ExperimentStats;

class ExperimentManager
{
    public const COOKIE_NAME = 'luminara_exp_sid';

    public const TOKEN_TTL_SECONDS = 604800; // 7 days

    /**
     * Request-scoped memoized subject identifier so multiple calls in one request share the same UUID.
     */
    protected ?string $resolvedSubjectId = null;

    protected ?int $resolvedRequestId = null;

    public function __construct(protected ExperimentRepository $experimentRepository) {}

    /**
     * Mint a tamper-proof HMAC-SHA256 subject token for cross-origin SDKs and WebForms.
     */
    public function signSubjectToken(string $subjectId, int $ttlSeconds = self::TOKEN_TTL_SECONDS): string
    {
        $payload = json_encode([
            'sid' => $subjectId,
            'exp' => time() + max(60, $ttlSeconds),
        ], JSON_THROW_ON_ERROR);

        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, $this->secretKey());

        return $encoded.'.'.$signature;
    }

    /**
     * Verify an HMAC-signed subject token and extract its subject identifier if authentic and unexpired.
     */
    public function verifySubjectToken(string $token): ?string
    {
        $parts = explode('.', trim($token), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$encoded, $signature] = $parts;
        $expected = hash_hmac('sha256', $encoded, $this->secretKey());

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);

        if (! is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (
            ! is_array($decoded)
            || ! isset($decoded['sid'], $decoded['exp'])
            || ! is_string($decoded['sid'])
            || $decoded['sid'] === ''
            || strlen($decoded['sid']) > 128
            || ! is_int($decoded['exp'])
            || $decoded['exp'] < time()
        ) {
            return null;
        }

        return $decoded['sid'];
    }

    /**
     * Resolve or mint a stable subject identifier for assignment requests.
     */
    public function resolveSubjectId(?Request $request = null): string
    {
        $request ??= request();
        $requestObjectId = spl_object_id($request);

        if ($this->resolvedSubjectId !== null && $this->resolvedRequestId === $requestObjectId) {
            return $this->resolvedSubjectId;
        }

        $verified = $this->resolveVerifiedSubject($request, false);

        if ($verified !== null) {
            $this->resolvedRequestId = $requestObjectId;

            return $this->resolvedSubjectId = $verified;
        }

        $queued = Cookie::queued(self::COOKIE_NAME);

        if ($queued !== null && $queued->getValue() !== '') {
            $this->resolvedRequestId = $requestObjectId;

            return $this->resolvedSubjectId = (string) $queued->getValue();
        }

        $generated = (string) Str::uuid();

        Cookie::queue(cookie(
            name: self::COOKIE_NAME,
            value: $generated,
            minutes: 60 * 24 * 90,
            path: '/',
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));

        $this->resolvedRequestId = $requestObjectId;

        return $this->resolvedSubjectId = $generated;
    }

    /**
     * Resolve an authenticated conversion subject (requires HMAC token or encrypted cookie in production).
     */
    public function resolveConversionSubjectId(?Request $request = null): ?string
    {
        return $this->resolveVerifiedSubject($request ?? request(), true);
    }

    /**
     * Shared verified subject resolver across assignment and conversion boundaries.
     */
    private function resolveVerifiedSubject(Request $request, bool $failClosedOnInvalidToken = true): ?string
    {
        $rawToken = trim((string) ($request->header('X-Experiment-Token', '') ?: $request->input('_exp_token', '')));

        if ($rawToken !== '') {
            $verified = $this->verifySubjectToken($rawToken);

            if ($verified !== null || $failClosedOnInvalidToken) {
                return $verified;
            }
        }

        $cookieValue = $request->cookie(self::COOKIE_NAME);

        if (is_string($cookieValue) && $cookieValue !== '' && strlen($cookieValue) <= 128) {
            return $cookieValue;
        }

        if (config('experiment.allow_unsigned_subject_header', app()->runningUnitTests())) {
            $explicitHeader = trim((string) $request->header('X-Experiment-Subject', ''));

            if ($explicitHeader !== '' && preg_match('/^[A-Za-z0-9_:\-.]{4,128}$/', $explicitHeader)) {
                return $explicitHeader;
            }
        }

        return null;
    }

    /**
     * Detect automated crawlers/monitors so synthetic probes do not pollute exposure denominators.
     */
    public function isBotRequest(?Request $request = null): bool
    {
        $request ??= request();
        $ua = (string) $request->userAgent();

        if ($ua === '') {
            return false;
        }

        return (bool) preg_match('/\b(bot|crawler|spider|slurp|headlesschrome|lighthouse|pingdom|uptimerobot|gtmetrix)\b/i', $ua);
    }

    /**
     * Compute a privacy-preserving HMAC-SHA256 digest of a raw subject identifier.
     */
    public function hashSubject(string $subjectId): string
    {
        return hash_hmac('sha256', $subjectId, $this->secretKey());
    }

    /**
     * Resolve the assigned variant key for an experiment code and optionally log an idempotent exposure.
     */
    public function variant(string $experimentCode, ?string $subjectId = null, bool $recordExposure = true): ?string
    {
        /** @var Experiment|null $experiment */
        $experiment = $this->experimentRepository->findOneByField('code', $experimentCode);

        if (! $experiment) {
            return null;
        }

        if ($experiment->status !== Experiment::STATUS_RUNNING) {
            return $experiment->resolvedStaticVariantKey();
        }

        $resolvedFromAmbientRequest = $subjectId === null;
        $subjectId ??= $this->resolveSubjectId();

        $assigned = Bucketer::assignVariant(
            experimentCode: $experiment->code,
            salt: $experiment->salt,
            subjectId: $subjectId,
            variants: $experiment->variants,
            trafficAllocation: $experiment->traffic_allocation,
        );

        if ($assigned === null) {
            return null;
        }

        if ($recordExposure && (! $resolvedFromAmbientRequest || ! $this->isBotRequest())) {
            $this->recordExposure($experiment, $assigned, $subjectId);
        }

        return $assigned;
    }

    /**
     * Check whether the subject is assigned to a specific variant of an experiment.
     */
    public function is(string $experimentCode, string $expectedVariant, ?string $subjectId = null): bool
    {
        return $this->variant($experimentCode, $subjectId, true) === $expectedVariant;
    }

    /**
     * Record an idempotent exposure event (at most 1 per experiment + subject).
     */
    public function recordExposure(Experiment $experiment, string $variantKey, string $subjectId): bool
    {
        return DB::table('experiment_events')->insertOrIgnore([
            'experiment_id' => $experiment->id,
            'variant_key' => $variantKey,
            'subject_hash' => $this->hashSubject($subjectId),
            'event_type' => 'exposure',
            'event_key' => 'default',
            'value' => 0,
            'metadata' => null,
            'created_at' => now(),
        ]) > 0;
    }

    /**
     * Record an idempotent conversion event strictly for subjects with a prior recorded exposure.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordConversion(
        string $experimentCode,
        ?string $subjectId = null,
        string $eventKey = 'default',
        float $value = 0.0,
        ?array $metadata = null
    ): bool {
        /** @var Experiment|null $experiment */
        $experiment = $this->experimentRepository->findOneByField('code', $experimentCode);

        if (! $experiment || $experiment->status !== Experiment::STATUS_RUNNING) {
            return false;
        }

        $subjectId ??= $this->resolveConversionSubjectId();

        if ($subjectId === null || $subjectId === '') {
            return false;
        }

        $subjectHash = $this->hashSubject($subjectId);

        /** @var string|null $exposedVariant */
        $exposedVariant = DB::table('experiment_events')
            ->where('experiment_id', $experiment->id)
            ->where('subject_hash', $subjectHash)
            ->where('event_type', 'exposure')
            ->value('variant_key');

        if ($exposedVariant === null) {
            return false;
        }

        return DB::table('experiment_events')->insertOrIgnore([
            'experiment_id' => $experiment->id,
            'variant_key' => $exposedVariant,
            'subject_hash' => $subjectHash,
            'event_type' => 'conversion',
            'event_key' => substr($eventKey, 0, 64),
            'value' => max(0.0, $value),
            'metadata' => $metadata !== null ? json_encode($metadata) : null,
            'created_at' => now(),
        ]) > 0;
    }

    /**
     * Attribute a conversion across all running experiments to which the current subject was exposed.
     *
     * @param  array<string, mixed>|Closure(): (array<string, mixed>|null)|null  $metadata
     */
    public function attributeSubjectConversions(
        string $subjectId,
        string $eventKey = 'lead_created',
        float $value = 0.0,
        array|Closure|null $metadata = null
    ): int {
        $subjectHash = $this->hashSubject($subjectId);

        $exposures = DB::table('experiment_events')
            ->join('experiments', 'experiments.id', '=', 'experiment_events.experiment_id')
            ->where('experiments.status', Experiment::STATUS_RUNNING)
            ->where('experiment_events.subject_hash', $subjectHash)
            ->where('experiment_events.event_type', 'exposure')
            ->select(['experiment_events.experiment_id', 'experiment_events.variant_key'])
            ->get();

        if ($exposures->isEmpty()) {
            return 0;
        }

        $resolvedMetadata = $metadata instanceof Closure ? $metadata() : $metadata;
        $encodedMetadata = is_array($resolvedMetadata) ? json_encode($resolvedMetadata) : null;
        $attributed = 0;

        foreach ($exposures as $exposure) {
            $inserted = DB::table('experiment_events')->insertOrIgnore([
                'experiment_id' => (int) $exposure->experiment_id,
                'variant_key' => (string) $exposure->variant_key,
                'subject_hash' => $subjectHash,
                'event_type' => 'conversion',
                'event_key' => substr($eventKey, 0, 64),
                'value' => max(0.0, $value),
                'metadata' => $encodedMetadata,
                'created_at' => now(),
            ]);

            $attributed += $inserted;
        }

        return $attributed;
    }

    /**
     * Compute statistical performance summary for a single Experiment model.
     *
     * @return array<string, mixed>
     */
    public function statsFor(Experiment $experiment): array
    {
        $batch = $this->statsForMany([$experiment]);

        return $batch[$experiment->id] ?? ExperimentStats::summarize($experiment->variants, []);
    }

    /**
     * Batch-compute statistical performance summaries for multiple Experiment models in a single SQL query.
     *
     * @param  iterable<Experiment>  $experiments
     * @return array<int, array<string, mixed>>
     */
    public function statsForMany(iterable $experiments): array
    {
        $list = [];

        foreach ($experiments as $exp) {
            $list[$exp->id] = $exp;
        }

        if ($list === []) {
            return [];
        }

        $rows = DB::table('experiment_events')
            ->whereIn('experiment_id', array_keys($list))
            ->selectRaw('experiment_id, variant_key, event_type, COUNT(DISTINCT subject_hash) as unique_subjects, COALESCE(SUM(value), 0) as total_value')
            ->groupBy('experiment_id', 'variant_key', 'event_type')
            ->get();

        /** @var array<int, array<string, array{exposures: int, conversions: int, total_value: float}>> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            $expId = (int) $row->experiment_id;
            $vKey = (string) $row->variant_key;
            $grouped[$expId][$vKey] ??= ['exposures' => 0, 'conversions' => 0, 'total_value' => 0.0];

            if ($row->event_type === 'exposure') {
                $grouped[$expId][$vKey]['exposures'] = (int) $row->unique_subjects;
            } elseif ($row->event_type === 'conversion') {
                $grouped[$expId][$vKey]['conversions'] = (int) $row->unique_subjects;
                $grouped[$expId][$vKey]['total_value'] = (float) $row->total_value;
            }
        }

        $results = [];

        foreach ($list as $expId => $experiment) {
            $results[$expId] = ExperimentStats::summarize($experiment->variants, $grouped[$expId] ?? []);
        }

        return $results;
    }

    /**
     * Retrieve a non-empty HMAC key, failing closed if APP_KEY is unset in production.
     */
    protected function secretKey(): string
    {
        $secret = trim((string) config('app.key', ''));

        if ($secret !== '') {
            return $secret;
        }

        if (app()->isProduction()) {
            throw new RuntimeException('APP_KEY must be configured in production for Experiment HMAC signing.');
        }

        return 'luminara-experiment-dev-fallback-key';
    }
}
