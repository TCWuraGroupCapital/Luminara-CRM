<?php

namespace Webkul\Ledger\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Ledger\Models\DataBlock;
use Webkul\Ledger\Models\LedgerEpoch;
use Webkul\Ledger\Support\LedgerSigner;
use Webkul\Ledger\Support\LedgerVerifier;

class LedgerController extends Controller
{
    public function index(Request $request, LedgerVerifier $verifier, LedgerSigner $signer): View|JsonResponse
    {
        abort_unless(
            bouncer()->hasPermission('settings.other_settings.ledger'),
            401,
            trans('admin::app.errors.unauthorized')
        );

        $health = $verifier->verifyChain(200);
        $epochs = LedgerEpoch::query()->orderByDesc('id')->limit(20)->get();
        $authorizedIds = bouncer()->getAuthorizedUserIds();

        $blocks = DataBlock::query()
            ->when($authorizedIds !== null, fn ($q) => $q->whereIn('actor_id', $authorizedIds))
            ->orderByDesc('block_height')
            ->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        $serverPubkey = $signer->publicKeyBase64Url();

        if ($request->expectsJson() || $request->ajax()) {
            $blocks->getCollection()->makeHidden(['canonical_payload']);

            return new JsonResponse([
                'health' => $health,
                'server_pubkey' => $serverPubkey,
                'epochs' => $epochs,
                'blocks' => $blocks,
            ]);
        }

        return view('ledger::admin.index', [
            'health' => $health,
            'serverPubkey' => $serverPubkey,
            'epochs' => $epochs,
            'blocks' => $blocks,
        ]);
    }

    public function seal(Request $request, LedgerVerifier $verifier): RedirectResponse|JsonResponse
    {
        abort_unless(
            bouncer()->hasPermission('settings.other_settings.ledger'),
            401,
            trans('admin::app.errors.unauthorized')
        );

        $epoch = $verifier->sealPendingEpoch();

        $message = $epoch !== null
            ? trans('ledger::app.admin.epoch-sealed', ['id' => $epoch->id, 'count' => $epoch->block_count])
            : trans('ledger::app.admin.epoch-empty');

        if ($request->expectsJson() || $request->ajax()) {
            return new JsonResponse([
                'sealed' => $epoch !== null,
                'message' => $message,
                'epoch' => $epoch,
            ]);
        }

        session()->flash('success', $message);

        return redirect()->route('admin.ledger.index');
    }
}
