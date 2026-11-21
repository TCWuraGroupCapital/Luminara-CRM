<?php

namespace Webkul\Ledger\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Ledger\Models\DataBlock;

/**
 * Removes personal data from ledger payloads for a deletion request without breaking the chain.
 *
 * A block's hash, its signature and its Merkle proof were all computed when it was written and
 * never need the payload again, so the payload can go. What must not be possible is to use
 * "redacted" to hide tampering: every redaction is first attested by a new signed block,
 * `ledger.redaction`, listing each redacted block's height, hash and a SHA-256 of its original
 * payload, and the verifier accepts a redacted block only when such a record names it.
 */
class LedgerRedactor
{
    public const EVENT = 'ledger.redaction';

    /**
     * Payload keys that survive redaction: signature material needed to re-check a passkey
     * co-signature, never personal data.
     */
    private const KEEP = ['signer_message_b64'];

    private const MIN_TERM_LENGTH = 4;

    public function __construct(private LedgerAppender $appender) {}

    /**
     * Unredacted blocks about the entity itself, or whose payload contains any of the terms
     * (case-insensitive). Short terms are ignored so a two-letter name can't match half the
     * ledger; matching errs towards redacting too much rather than too little.
     *
     * @param  array<int, string|null>  $terms
     * @param  array<string, array<int, int|string>>  $related  other entities whose blocks go too, e.g. ['activity' => [4, 9]]
     * @return Collection<int, DataBlock>
     */
    public function findMentions(string $entityType, string $entityId, array $terms, array $related = []): Collection
    {
        $terms = collect($terms)
            ->map(static fn (?string $term): string => mb_strtolower(trim((string) $term)))
            ->filter(static fn (string $term): bool => mb_strlen($term) >= self::MIN_TERM_LENGTH)
            ->unique()
            ->values();

        return DataBlock::query()
            ->whereNull('redacted_at')
            ->where('event_name', '!=', self::EVENT)
            ->where(function ($query) use ($entityType, $entityId, $terms, $related): void {
                $query->where(static fn ($exact) => $exact->where('entity_type', $entityType)->where('entity_id', $entityId));

                foreach ($related as $type => $ids) {
                    if ($ids !== []) {
                        $query->orWhere(static fn ($linked) => $linked
                            ->where('entity_type', $type)
                            ->whereIn('entity_id', array_map('strval', $ids)));
                    }
                }

                foreach ($terms as $term) {
                    $query->orWhereRaw(
                        'LOWER(CAST(canonical_payload AS CHAR)) LIKE ?',
                        ['%'.addcslashes($term, '%_\\').'%']
                    );
                }
            })
            ->orderBy('block_height')
            ->get();
    }

    /**
     * Attest, then redact. Returns the attesting block, or null when there was nothing to do.
     *
     * @param  Collection<int, DataBlock>  $blocks
     */
    public function redact(Collection $blocks, string $entityType, string $entityId, string $reason, ?int $actorId = null): ?DataBlock
    {
        $blocks = $blocks->reject(static fn (DataBlock $block): bool => $block->isRedacted())->values();

        if ($blocks->isEmpty()) {
            return null;
        }

        $entries = $blocks
            ->map(static fn (DataBlock $block): array => [
                'height' => $block->block_height,
                'block_hash' => $block->block_hash,
                'payload_sha256' => hash('sha256', CanonicalJson::encode($block->canonical_payload)),
            ])
            ->all();

        return DB::transaction(function () use ($blocks, $entries, $entityType, $entityId, $reason, $actorId): DataBlock {
            $record = $this->appender->append(
                self::EVENT,
                $entityType,
                $entityId,
                ['reason' => $reason, 'redacted' => $entries],
                'human',
                $actorId,
            );

            $now = now()->utc()->format('Y-m-d H:i:s.u');

            foreach ($blocks as $block) {
                $kept = array_intersect_key($block->canonical_payload, array_flip(self::KEEP));

                // Data Blocks refuse Eloquent updates by design; this is the one narrow,
                // attested path that may touch a written block, and only its payload.
                DB::table('data_blocks')
                    ->where('id', $block->id)
                    ->whereNull('redacted_at')
                    ->update([
                        'canonical_payload' => json_encode(['redacted' => true] + $kept, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                        'redacted_at' => $now,
                        'redaction_receipt' => $record->receipt_code,
                    ]);
            }

            return $record;
        });
    }

    /**
     * Height => block hash for every redaction attested by a signed `ledger.redaction` block,
     * with the attesting block's height, so the verifier can insist the attestation came later.
     *
     * @return array<int, array{block_hash: string, attested_at_height: int}>
     */
    public static function attestations(): array
    {
        $map = [];

        DataBlock::query()
            ->where('event_name', self::EVENT)
            ->whereNull('redacted_at')
            ->orderBy('block_height')
            ->each(static function (DataBlock $record) use (&$map): void {
                foreach ((array) ($record->canonical_payload['redacted'] ?? []) as $entry) {
                    if (is_array($entry) && isset($entry['height'], $entry['block_hash'])) {
                        $map[(int) $entry['height']] ??= [
                            'block_hash' => (string) $entry['block_hash'],
                            'attested_at_height' => $record->block_height,
                        ];
                    }
                }
            });

        return $map;
    }

    /**
     * Whether a redacted block is covered by a later signed attestation naming its exact hash.
     *
     * @param  array<int, array{block_hash: string, attested_at_height: int}>  $attestations
     */
    public static function isAttested(DataBlock $block, array $attestations): bool
    {
        $attestation = $attestations[$block->block_height] ?? null;

        return $attestation !== null
            && $attestation['attested_at_height'] > $block->block_height
            && hash_equals($attestation['block_hash'], $block->block_hash);
    }
}
