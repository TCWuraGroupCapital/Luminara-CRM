<?php

namespace Webkul\Ledger\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Ledger\Contracts\AnchorDriver;
use Webkul\Ledger\Models\DataBlock;
use Webkul\Ledger\Models\LedgerEpoch;

class LedgerVerifier
{
    public function __construct(
        private readonly LedgerSigner $signer,
        private readonly LedgerAppender $appender,
    ) {}

    /**
     * Seal up to epoch_max_blocks unsealed Data Blocks into a signed RFC 6962 Merkle Epoch.
     */
    public function sealPendingEpoch(): ?LedgerEpoch
    {
        if (! Schema::hasTable('data_blocks') || ! Schema::hasTable('ledger_epochs')) {
            return null;
        }

        /** @var AnchorDriver $driver */
        $driver = app(AnchorDriver::class);

        $epoch = DB::transaction(function () use ($driver): ?LedgerEpoch {
            $maxBlocks = max(1, (int) config('ledger.epoch_max_blocks', 1000));

            $blocks = DataBlock::query()
                ->whereNull('epoch_id')
                ->orderBy('block_height')
                ->limit($maxBlocks)
                ->lockForUpdate()
                ->get();

            if ($blocks->isEmpty()) {
                return null;
            }

            /** @var DataBlock $firstBlock */
            $firstBlock = $blocks->first();
            /** @var DataBlock $lastBlock */
            $lastBlock = $blocks->last();

            $startHeight = $firstBlock->block_height;
            $endHeight = $lastBlock->block_height;
            $blockCount = $blocks->count();

            /** @var array<int, string> $hashes */
            $hashes = $blocks->pluck('block_hash')->all();
            $merkleRoot = MerkleTree::root($hashes);

            $previousEpoch = LedgerEpoch::query()->orderByDesc('id')->lockForUpdate()->first();
            $prevEpochRoot = $previousEpoch !== null ? $previousEpoch->merkle_root : MerkleTree::EMPTY_ROOT;

            $epochMessage = "{$startHeight}|{$endHeight}|{$merkleRoot}|{$prevEpochRoot}";
            $signed = $this->signer->signHash($epochMessage);
            $sealedAt = now()->utc();

            $epoch = LedgerEpoch::create([
                'start_height' => $startHeight,
                'end_height' => $endHeight,
                'block_count' => $blockCount,
                'merkle_root' => $merkleRoot,
                'prev_epoch_root' => $prevEpochRoot,
                'server_signature' => $signed['proof'],
                'anchor_driver' => $driver->name(),
                'anchor_reference' => null,
                'sealed_at' => $sealedAt,
            ]);

            DB::table('data_blocks')
                ->whereBetween('block_height', [$startHeight, $endHeight])
                ->update(['epoch_id' => $epoch->id]);

            return $epoch;
        });

        if (! $epoch instanceof LedgerEpoch) {
            return null;
        }

        $anchorReference = $driver->anchor($epoch);

        if ($anchorReference !== null && $anchorReference !== '') {
            DB::table('ledger_epochs')
                ->where('id', $epoch->id)
                ->update(['anchor_reference' => $anchorReference]);

            $epoch->setAttribute('anchor_reference', $anchorReference);
            $epoch->syncOriginalAttribute('anchor_reference');
        }

        return $epoch;
    }

    /**
     * Recompute hashes, check chain continuity, verify block signatures, and verify all sealed Merkle epochs.
     *
     * @return array{ok: bool, checked_blocks: int, checked_epochs: int, head_height: int, head_hash: string, errors: array<int, string>}
     */
    public function verifyChain(?int $limit = null): array
    {
        $head = $this->appender->head();

        if (! Schema::hasTable('data_blocks')) {
            return [
                'ok' => true,
                'checked_blocks' => 0,
                'checked_epochs' => 0,
                'head_height' => $head['height'],
                'head_hash' => $head['hash'],
                'errors' => [],
            ];
        }

        $errors = [];

        if ($limit !== null && $limit > 0) {
            $blocks = DataBlock::query()
                ->orderByDesc('block_height')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();
        } else {
            $blocks = DataBlock::query()
                ->orderBy('block_height')
                ->get();
        }

        $checkedBlocks = $blocks->count();
        $expectedPrevHash = null;
        $expectedHeight = null;

        if ($blocks->isNotEmpty()) {
            /** @var DataBlock $first */
            $first = $blocks->first();

            if ($first->block_height === 1) {
                $expectedPrevHash = MerkleTree::EMPTY_ROOT;
                $expectedHeight = 1;
            } else {
                $priorHash = DataBlock::query()
                    ->where('block_height', $first->block_height - 1)
                    ->value('block_hash');

                if (! is_string($priorHash)) {
                    $errors[] = "Block #{$first->block_height} is missing predecessor block #".($first->block_height - 1).'.';
                } else {
                    $expectedPrevHash = $priorHash;
                }

                $expectedHeight = $first->block_height;
            }
        }

        $attestations = $blocks->contains(static fn (DataBlock $block): bool => $block->isRedacted())
            ? LedgerRedactor::attestations()
            : [];

        foreach ($blocks as $block) {
            if ($expectedHeight !== null && $block->block_height !== $expectedHeight) {
                $errors[] = "Block height gap detected: expected #{$expectedHeight}, found #{$block->block_height}.";
            }

            if ($expectedPrevHash !== null && ! hash_equals($expectedPrevHash, $block->prev_block_hash)) {
                $errors[] = "Block #{$block->block_height} prev_block_hash breaks continuity.";
            }

            if ($block->isRedacted()) {
                // The payload was removed for a deletion request. Its hash can no longer be
                // recomputed, so it must be named by a later signed redaction record instead.
                if (! LedgerRedactor::isAttested($block, $attestations)) {
                    $errors[] = "Block #{$block->block_height} ({$block->receipt_code}) is marked redacted without a signed redaction record.";
                }
            } elseif (! hash_equals(self::computeBlockHash($block), $block->block_hash)) {
                $errors[] = "Block #{$block->block_height} ({$block->receipt_code}) SHA-256 hash mismatch.";
            }

            if (! $this->verifyBlockSignature($block)) {
                $errors[] = "Block #{$block->block_height} ({$block->receipt_code}) {$block->signer_alg} signature verification failed.";
            }

            $expectedPrevHash = $block->block_hash;
            $expectedHeight = $block->block_height + 1;
        }

        if ($blocks->isNotEmpty()) {
            /** @var DataBlock $latestBlock */
            $latestBlock = $blocks->last();

            if ($head['height'] !== $latestBlock->block_height || ! hash_equals($head['hash'], $latestBlock->block_hash)) {
                $errors[] = 'Ledger head pointer diverges from latest Data Block.';
            }
        } elseif ($head['height'] !== 0 || $head['hash'] !== MerkleTree::EMPTY_ROOT) {
            $errors[] = 'Ledger head is non-zero while data_blocks table is empty.';
        }

        $checkedEpochs = 0;

        if (Schema::hasTable('ledger_epochs')) {
            $epochs = LedgerEpoch::query()->orderBy('id')->get();
            $checkedEpochs = $epochs->count();
            $expectedPrevEpochRoot = MerkleTree::EMPTY_ROOT;

            foreach ($epochs as $epoch) {
                if (! hash_equals($expectedPrevEpochRoot, $epoch->prev_epoch_root)) {
                    $errors[] = "Epoch #{$epoch->id} prev_epoch_root breaks epoch continuity.";
                }

                /** @var array<int, string> $epochHashes */
                $epochHashes = DataBlock::query()
                    ->whereBetween('block_height', [$epoch->start_height, $epoch->end_height])
                    ->orderBy('block_height')
                    ->pluck('block_hash')
                    ->all();

                if (count($epochHashes) !== $epoch->block_count) {
                    $errors[] = "Epoch #{$epoch->id} block_count ({$epoch->block_count}) does not match actual blocks (".count($epochHashes).').';
                }

                $recomputedRoot = MerkleTree::root($epochHashes);

                if (! hash_equals($recomputedRoot, $epoch->merkle_root)) {
                    $errors[] = "Epoch #{$epoch->id} Merkle root mismatch.";
                }

                $epochMsg = "{$epoch->start_height}|{$epoch->end_height}|{$epoch->merkle_root}|{$epoch->prev_epoch_root}";

                if (! $this->signer->verify('ed25519', $this->signer->publicKeyBase64Url(), $epochMsg, $epoch->server_signature)) {
                    $errors[] = "Epoch #{$epoch->id} server signature verification failed.";
                }

                $expectedPrevEpochRoot = $epoch->merkle_root;
            }
        }

        return [
            'ok' => $errors === [],
            'checked_blocks' => $checkedBlocks,
            'checked_epochs' => $checkedEpochs,
            'head_height' => $head['height'],
            'head_hash' => $head['hash'],
            'errors' => $errors,
        ];
    }

    /**
     * Look up a Data Block by receipt_code or block_hash and verify its hash, signature, and Merkle inclusion proof.
     *
     * @return array{
     *     verified: bool,
     *     block: DataBlock,
     *     recomputed_hash: string,
     *     hash_valid: bool,
     *     signature_valid: bool,
     *     merkle_valid: ?bool,
     *     merkle_proof: list<array{direction: 'L'|'R', sibling: string}>,
     *     epoch: ?array<string, mixed>
     * }|null
     */
    public function verifyReceipt(string $receiptCodeOrHash): ?array
    {
        if (! Schema::hasTable('data_blocks')) {
            return null;
        }

        $needle = trim($receiptCodeOrHash);

        if ($needle === '') {
            return null;
        }

        if (strlen($needle) === 64 && ctype_xdigit($needle)) {
            $block = DataBlock::query()->where('block_hash', strtolower($needle))->first();
        } else {
            $block = DataBlock::query()
                ->where('receipt_code', strtoupper($needle))
                ->orWhere('block_hash', strtolower($needle))
                ->first();
        }

        if ($block === null) {
            return null;
        }

        $recomputedHash = self::computeBlockHash($block);
        $hashValid = $block->isRedacted()
            ? LedgerRedactor::isAttested($block, LedgerRedactor::attestations())
            : hash_equals($recomputedHash, $block->block_hash);
        $signatureValid = $this->verifyBlockSignature($block);

        $merkleProof = [];
        $merkleValid = null;
        $epochPayload = null;

        if ($block->epoch_id !== null && Schema::hasTable('ledger_epochs')) {
            $epoch = LedgerEpoch::query()->find($block->epoch_id);

            if ($epoch === null) {
                $merkleValid = false;
            } else {
                /** @var array<int, string> $epochHashes */
                $epochHashes = DataBlock::query()
                    ->whereBetween('block_height', [$epoch->start_height, $epoch->end_height])
                    ->orderBy('block_height')
                    ->pluck('block_hash')
                    ->all();

                $leafIndex = array_search($block->block_hash, $epochHashes, true);

                if ($leafIndex === false) {
                    $merkleValid = false;
                } else {
                    $merkleProof = MerkleTree::proof($epochHashes, (int) $leafIndex);
                    $proofValid = MerkleTree::verifyProof($block->block_hash, $merkleProof, $epoch->merkle_root);
                    $rootMatches = hash_equals(MerkleTree::root($epochHashes), $epoch->merkle_root);
                    $epochMsg = "{$epoch->start_height}|{$epoch->end_height}|{$epoch->merkle_root}|{$epoch->prev_epoch_root}";
                    $epochSigValid = $this->signer->verify(
                        'ed25519',
                        $this->signer->publicKeyBase64Url(),
                        $epochMsg,
                        $epoch->server_signature
                    );

                    $merkleValid = $proofValid && $rootMatches && $epochSigValid;
                }

                $epochPayload = [
                    'id' => $epoch->id,
                    'start_height' => $epoch->start_height,
                    'end_height' => $epoch->end_height,
                    'block_count' => $epoch->block_count,
                    'merkle_root' => $epoch->merkle_root,
                    'prev_epoch_root' => $epoch->prev_epoch_root,
                    'server_signature' => $epoch->server_signature,
                    'anchor_driver' => $epoch->anchor_driver,
                    'anchor_reference' => $epoch->anchor_reference,
                    'sealed_at' => $epoch->sealed_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
                ];
            }
        }

        return [
            'verified' => $hashValid && $signatureValid && ($merkleValid !== false),
            'block' => $block,
            'recomputed_hash' => $recomputedHash,
            'hash_valid' => $hashValid,
            'signature_valid' => $signatureValid,
            'merkle_valid' => $merkleValid,
            'merkle_proof' => $merkleProof,
            'epoch' => $epochPayload,
        ];
    }

    /**
     * Deterministically recompute a DataBlock's SHA-256 hash from its stored canonical components.
     */
    public static function computeBlockHash(DataBlock $block): string
    {
        $iso = $block->created_at->utc()->format('Y-m-d\TH:i:s.u\Z');
        $jcs = CanonicalJson::encode($block->canonical_payload);

        return hash(
            'sha256',
            "{$block->block_height}|{$block->prev_block_hash}|{$block->event_name}|{$block->entity_type}:{$block->entity_id}|{$iso}|{$jcs}"
        );
    }

    public function verifyBlockSignature(DataBlock $block): bool
    {
        if ($this->signer->verify($block->signer_alg, $block->signer_pubkey, $block->block_hash, $block->signer_proof)) {
            return true;
        }

        $payload = $block->canonical_payload;

        if (
            str_contains($block->signer_proof, '.')
            && isset($payload['signer_message_b64'])
            && is_string($payload['signer_message_b64'])
        ) {
            [$userProof, $coSig] = explode('.', $block->signer_proof, 2);
            $signedMessage = LedgerSigner::base64UrlDecode($payload['signer_message_b64']);

            if ($signedMessage === null || $signedMessage === '' || $userProof === '' || $coSig === '') {
                return false;
            }

            if (! $this->signer->verify($block->signer_alg, $block->signer_pubkey, $signedMessage, $userProof)) {
                return false;
            }

            $coSigDigest = hash('sha256', "{$block->block_hash}|{$block->signer_alg}|{$block->signer_pubkey}|{$userProof}");

            return $this->signer->verify(
                'ed25519',
                $this->signer->publicKeyBase64Url(),
                $coSigDigest,
                $coSig
            );
        }

        return false;
    }
}
