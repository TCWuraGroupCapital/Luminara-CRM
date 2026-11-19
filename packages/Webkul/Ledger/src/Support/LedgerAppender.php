<?php

namespace Webkul\Ledger\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Webkul\Ledger\Models\DataBlock;

class LedgerAppender
{
    public function __construct(private readonly LedgerSigner $signer) {}

    /**
     * Return the current chain head height and hash.
     *
     * @return array{height: int, hash: string, updated_at: ?string}
     */
    public function head(): array
    {
        if (! Schema::hasTable('ledger_heads')) {
            return [
                'height' => 0,
                'hash' => MerkleTree::EMPTY_ROOT,
                'updated_at' => null,
            ];
        }

        $streamKey = (string) config('ledger.stream_key', 'global');
        $row = DB::table('ledger_heads')->where('stream_key', $streamKey)->first();

        if ($row === null) {
            return [
                'height' => 0,
                'hash' => MerkleTree::EMPTY_ROOT,
                'updated_at' => null,
            ];
        }

        return [
            'height' => (int) $row->head_height,
            'hash' => (string) $row->head_hash,
            'updated_at' => $row->updated_at !== null ? (string) $row->updated_at : null,
        ];
    }

    /**
     * Append a canonical, hash-chained, cryptographically signed Data Block inside a serialized DB transaction.
     *
     * @param  array<array-key, mixed>  $payload
     * @param  array<string, mixed>|null  $customSigner
     */
    public function append(
        string $eventName,
        string $entityType,
        string|int $entityId,
        array $payload,
        string $actorKind = 'human',
        ?int $actorId = null,
        ?int $agentPassportId = null,
        ?array $customSigner = null,
    ): DataBlock {
        return DB::transaction(function () use (
            $eventName,
            $entityType,
            $entityId,
            $payload,
            $actorKind,
            $actorId,
            $agentPassportId,
            $customSigner,
        ): DataBlock {
            $streamKey = (string) config('ledger.stream_key', 'global');

            DB::table('ledger_heads')->insertOrIgnore([
                'stream_key' => $streamKey,
                'head_height' => 0,
                'head_hash' => MerkleTree::EMPTY_ROOT,
                'updated_at' => null,
            ]);

            $head = DB::table('ledger_heads')
                ->where('stream_key', $streamKey)
                ->lockForUpdate()
                ->first();

            $height = ((int) ($head->head_height ?? 0)) + 1;
            $prevHash = (string) ($head->head_hash ?? MerkleTree::EMPTY_ROOT);

            $createdAt = now()->utc();
            $iso = $createdAt->format('Y-m-d\TH:i:s.u\Z');

            $scrubbed = CanonicalJson::scrubSecrets($payload);
            $verifiedExternalSigner = $this->prevalidateExternalSigner($customSigner);

            if ($verifiedExternalSigner !== null) {
                $scrubbed['signer_message_b64'] = $verifiedExternalSigner['message_b64'];
            } else {
                unset($scrubbed['signer_message_b64']);
            }

            $initialJcs = CanonicalJson::encode($scrubbed);
            /** @var array<array-key, mixed> $normalizedPayload */
            $normalizedPayload = json_decode($initialJcs, true, 512, JSON_THROW_ON_ERROR);
            $jcs = CanonicalJson::encode($normalizedPayload);

            $entityIdStr = (string) $entityId;
            $blockHash = hash('sha256', "{$height}|{$prevHash}|{$eventName}|{$entityType}:{$entityIdStr}|{$iso}|{$jcs}");
            $receiptCode = 'LUM-'.strtoupper((string) Str::ulid());

            $signed = $this->resolveSignature($blockHash, $customSigner, $verifiedExternalSigner);

            $resolvedActorId = $actorId;

            if (
                $resolvedActorId !== null
                && ($resolvedActorId <= 0 || ! DB::table('users')->where('id', $resolvedActorId)->exists())
            ) {
                $resolvedActorId = null;
            }

            $block = DataBlock::create([
                'block_height' => $height,
                'receipt_code' => $receiptCode,
                'prev_block_hash' => $prevHash,
                'block_hash' => $blockHash,
                'event_name' => $eventName,
                'entity_type' => $entityType,
                'entity_id' => $entityIdStr,
                'actor_kind' => $actorKind,
                'actor_id' => $resolvedActorId,
                'agent_passport_id' => $agentPassportId,
                'canonical_payload' => $normalizedPayload,
                'signer_alg' => $signed['alg'],
                'signer_pubkey' => $signed['pubkey'],
                'signer_proof' => $signed['proof'],
                'epoch_id' => null,
                'created_at' => $createdAt,
            ]);

            DB::table('ledger_heads')
                ->where('stream_key', $streamKey)
                ->update([
                    'head_height' => $height,
                    'head_hash' => $blockHash,
                    'updated_at' => $createdAt->format('Y-m-d H:i:s'),
                ]);

            return $block;
        });
    }

    /**
     * @param  array<string, mixed>|null  $customSigner
     * @return array{alg: string, pubkey: string, proof: string, message_b64: string}|null
     */
    private function prevalidateExternalSigner(?array $customSigner): ?array
    {
        if ($customSigner === null) {
            return null;
        }

        $alg = strtolower(trim((string) ($customSigner['alg'] ?? '')));
        $pubkey = trim((string) ($customSigner['pubkey'] ?? ''));
        $userProof = trim((string) ($customSigner['proof'] ?? ''));

        if ($alg === '' || $pubkey === '' || $userProof === '') {
            return null;
        }

        $messageB64 = null;

        if (isset($customSigner['message']) && is_string($customSigner['message']) && $customSigner['message'] !== '') {
            $messageB64 = LedgerSigner::base64UrlEncode($customSigner['message']);
        } elseif (isset($customSigner['message_b64']) && is_string($customSigner['message_b64'])) {
            $messageB64 = trim($customSigner['message_b64']);
        } elseif (isset($customSigner['signed_data']) && is_string($customSigner['signed_data'])) {
            $messageB64 = trim($customSigner['signed_data']);
        }

        if ($messageB64 === null || $messageB64 === '') {
            return null;
        }

        $signedMessage = LedgerSigner::base64UrlDecode($messageB64);

        if ($signedMessage === null || $signedMessage === '' || ! $this->signer->verify($alg, $pubkey, $signedMessage, $userProof)) {
            return null;
        }

        return [
            'alg' => $alg,
            'pubkey' => $pubkey,
            'proof' => $userProof,
            'message_b64' => $messageB64,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $customSigner
     * @param  array{alg: string, pubkey: string, proof: string, message_b64: string}|null  $verifiedExternalSigner
     * @return array{alg: string, pubkey: string, proof: string}
     */
    private function resolveSignature(string $blockHash, ?array $customSigner, ?array $verifiedExternalSigner = null): array
    {
        if ($verifiedExternalSigner !== null) {
            $coSigDigest = hash(
                'sha256',
                "{$blockHash}|{$verifiedExternalSigner['alg']}|{$verifiedExternalSigner['pubkey']}|{$verifiedExternalSigner['proof']}"
            );
            $coSig = $this->signer->signHash($coSigDigest)['proof'];

            return [
                'alg' => $verifiedExternalSigner['alg'],
                'pubkey' => $verifiedExternalSigner['pubkey'],
                'proof' => "{$verifiedExternalSigner['proof']}.{$coSig}",
            ];
        }

        if ($customSigner === null) {
            return $this->signer->signHash($blockHash);
        }

        $alg = strtolower(trim((string) ($customSigner['alg'] ?? '')));
        $pubkey = trim((string) ($customSigner['pubkey'] ?? ''));

        if ($alg === '' || $pubkey === '') {
            return $this->signer->signHash($blockHash);
        }

        if (isset($customSigner['sign']) && is_callable($customSigner['sign'])) {
            $proof = trim((string) ($customSigner['sign'])($blockHash));

            if ($proof !== '' && $this->signer->verify($alg, $pubkey, $blockHash, $proof)) {
                return [
                    'alg' => $alg,
                    'pubkey' => $pubkey,
                    'proof' => $proof,
                ];
            }

            return $this->signer->signHash($blockHash);
        }

        $userProof = trim((string) ($customSigner['proof'] ?? ''));

        if ($userProof !== '' && $this->signer->verify($alg, $pubkey, $blockHash, $userProof)) {
            return [
                'alg' => $alg,
                'pubkey' => $pubkey,
                'proof' => $userProof,
            ];
        }

        return $this->signer->signHash($blockHash);
    }
}
