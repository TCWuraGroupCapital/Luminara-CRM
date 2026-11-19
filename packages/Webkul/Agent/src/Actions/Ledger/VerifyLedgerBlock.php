<?php

namespace Webkul\Agent\Actions\Ledger;

use Illuminate\Support\Facades\Schema;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Ledger\Support\LedgerVerifier;

class VerifyLedgerBlock extends AgentAction
{
    public function __construct(private LedgerVerifier $verifier) {}

    public function name(): string
    {
        return 'ledger_verify';
    }

    public function summary(): string
    {
        return 'Verify a cryptographic receipt_code, block_hash, or the current Data Block chain head.';
    }

    public function permission(): string
    {
        return 'leads';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'receipt_code' => ['nullable', 'string', 'max:40'],
            'block_hash' => ['nullable', 'string', 'max:64'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function handle(array $params): array
    {
        if (! Schema::hasTable('data_blocks')) {
            return [
                'ledger_enabled' => false,
                'verified' => false,
                'message' => 'Ledger tables are not initialized in this workspace.',
            ];
        }

        $receiptCode = ! empty($params['receipt_code']) ? trim((string) $params['receipt_code']) : null;
        $blockHash = ! empty($params['block_hash']) ? trim((string) $params['block_hash']) : null;

        if ($receiptCode === null && $blockHash === null) {
            $chain = $this->verifier->verifyChain((int) ($params['limit'] ?? 200));

            return ['ledger_enabled' => true, 'verified' => $chain['ok']] + $chain;
        }

        $result = $this->verifier->verifyReceipt($receiptCode ?? (string) $blockHash);

        if ($result === null) {
            return [
                'ledger_enabled' => true,
                'verified' => false,
                'message' => 'No Data Block matches the given receipt code or block hash.',
            ];
        }

        unset($result['block']);

        return ['ledger_enabled' => true] + $result;
    }
}
