<?php

namespace Webkul\Ledger\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Webkul\Ledger\Models\DataBlock;
use Webkul\Ledger\Support\LedgerVerifier;
use Webkul\User\Models\User;

class LedgerProofController extends Controller
{
    public function show(string $codeOrHash, LedgerVerifier $verifier): JsonResponse
    {
        $verification = $verifier->verifyReceipt($codeOrHash);

        if ($verification === null) {
            return new JsonResponse(['message' => 'Receipt or block hash not found.'], 404);
        }

        $block = $verification['block'];

        $response = [
            'verified' => $verification['verified'],
            'receipt_code' => $block->receipt_code,
            'block_height' => $block->block_height,
            'block_hash' => $block->block_hash,
            'prev_block_hash' => $block->prev_block_hash,
            'timestamp' => $block->created_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'event_name' => $block->event_name,
            'entity_type' => $block->entity_type,
            'actor_kind' => $block->actor_kind,
            'signer_alg' => $block->signer_alg,
            'signer_pubkey' => $block->signer_pubkey,
            'signer_proof' => $block->signer_proof,
            'hash_valid' => $verification['hash_valid'],
            'signature_valid' => $verification['signature_valid'],
            'merkle_valid' => $verification['merkle_valid'],
            'merkle_proof' => $verification['merkle_proof'],
            'epoch' => $verification['epoch'],
        ];

        if ($this->viewerCanInspectPayload($block)) {
            $response['entity_summary'] = $this->buildEntitySummary($block);
        }

        return new JsonResponse($response);
    }

    private function viewerCanInspectPayload(DataBlock $block): bool
    {
        $request = request();
        $hasBearer = is_string($request->bearerToken()) && $request->bearerToken() !== '';
        $viewer = $hasBearer ? $this->resolveBearerViewer() : auth()->guard('user')->user();

        if (! $viewer instanceof User && ! $hasBearer) {
            $viewer = $this->resolveBearerViewer();
        }

        if (! $viewer instanceof User || ! $viewer->getAttribute('status')) {
            return false;
        }

        if (! $hasBearer && ! $this->sessionMfaSatisfied((int) $viewer->getKey())) {
            return false;
        }

        $role = $viewer->getRelationValue('role');

        if (! $role instanceof Model) {
            return false;
        }

        $hasPermission = $role->getAttribute('permission_type') === 'all'
            || in_array('settings.other_settings.ledger', (array) $role->getAttribute('permissions'), true);

        if (! $hasPermission) {
            return false;
        }

        $viewPermission = (string) ($viewer->getAttribute('view_permission') ?: 'individual');

        if ($viewPermission === 'global') {
            return true;
        }

        $viewerId = (int) $viewer->getKey();

        if ($block->actor_id !== null && (int) $block->actor_id === $viewerId) {
            return true;
        }

        $ownerId = $block->canonical_payload['user_id'] ?? $block->actor_id;

        if (! is_numeric($ownerId)) {
            return false;
        }

        if ((int) $ownerId === $viewerId) {
            return true;
        }

        if ($viewPermission !== 'group') {
            return false;
        }

        $groupIds = $viewer->groups()->pluck('groups.id')->all();

        return $groupIds !== []
            && DB::table('user_groups')
                ->whereIn('group_id', $groupIds)
                ->where('user_id', (int) $ownerId)
                ->exists();
    }

    private function sessionMfaSatisfied(int $userId): bool
    {
        if (! Schema::hasTable('user_mfa_settings')) {
            return true;
        }

        $mfaEnabled = (bool) DB::table('user_mfa_settings')
            ->where('user_id', $userId)
            ->value('mfa_enabled');

        if (! $mfaEnabled && ! (bool) config('security.require_admin_mfa', false)) {
            return true;
        }

        $req = request();
        $verifiedUserId = $req->hasSession()
            ? (int) $req->session()->get('security.mfa_verified_user_id', 0)
            : (int) session('security.mfa_verified_user_id', 0);

        return $mfaEnabled && $verifiedUserId === $userId;
    }

    private function resolveBearerViewer(): ?User
    {
        $bearer = request()->bearerToken();

        if (! is_string($bearer) || $bearer === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearer);

        if ($token === null) {
            return null;
        }

        $expiration = config('sanctum.expiration');
        $expired = ($token->expires_at !== null && $token->expires_at->isPast())
            || (is_numeric($expiration) && (int) $expiration > 0 && $token->created_at?->lte(now()->subMinutes((int) $expiration)));

        if ($expired || (! $token->can('agent') && ! $token->can('tma'))) {
            return null;
        }

        if (Schema::hasTable('agent_passports')) {
            $passport = DB::table('agent_passports')
                ->where('token_id', $token->getKey())
                ->first(['status', 'allowed_actions']);

            if ($passport !== null) {
                if (! (bool) $passport->status) {
                    return null;
                }

                if ($passport->allowed_actions !== null) {
                    $patterns = json_decode((string) $passport->allowed_actions, true);

                    if (! is_array($patterns) || ! $this->passportAllowsLedgerRead($patterns)) {
                        return null;
                    }
                }
            }
        }

        $tokenable = $token->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }

    /**
     * @param  array<array-key, mixed>  $patterns
     */
    private function passportAllowsLedgerRead(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $rule = (string) $pattern;

            if (Str::is($rule, 'ledger_verify') || Str::is($rule, 'ledger_history')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEntitySummary(DataBlock $block): array
    {
        $payload = $block->canonical_payload;
        $label = null;

        foreach (['title', 'name', 'subject', 'sku', 'action'] as $candidateKey) {
            if (isset($payload[$candidateKey]) && is_scalar($payload[$candidateKey])) {
                $label = (string) $payload[$candidateKey];

                break;
            }
        }

        return [
            'entity_type' => $block->entity_type,
            'entity_id' => $block->entity_id,
            'label' => $label,
            'canonical_payload' => $payload,
            'redacted_at' => $block->redacted_at?->utc()->toIso8601String(),
            'redaction_receipt' => $block->redaction_receipt,
        ];
    }
}
