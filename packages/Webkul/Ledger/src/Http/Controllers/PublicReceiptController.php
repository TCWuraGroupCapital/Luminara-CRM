<?php

namespace Webkul\Ledger\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Webkul\Ledger\Support\LedgerVerifier;

class PublicReceiptController extends Controller
{
    public function show(string $receiptCode, LedgerVerifier $verifier): View
    {
        $verification = $verifier->verifyReceipt($receiptCode);

        if ($verification === null) {
            abort(404);
        }

        $block = $verification['block'];
        $eventKey = str_replace('.', '-', $block->event_name);
        $translationKey = 'ledger::app.events.'.$eventKey;
        $translated = trans($translationKey);

        $eventSummary = $translated !== $translationKey
            ? $translated
            : trans('ledger::app.events.generic', ['event' => $block->event_name]);

        return view('ledger::receipts.show', [
            'verification' => $verification,
            'block' => $block,
            'eventSummary' => $eventSummary,
        ]);
    }
}
