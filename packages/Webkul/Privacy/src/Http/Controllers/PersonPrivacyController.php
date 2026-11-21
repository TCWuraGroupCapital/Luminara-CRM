<?php

namespace Webkul\Privacy\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Webkul\Privacy\Support\PersonDataExporter;
use Webkul\Privacy\Support\PersonEraser;
use Webkul\Security\Support\SecurityAuditRecorder;

class PersonPrivacyController extends Controller
{
    public const PERMISSION = 'contacts.persons.privacy';

    public function __construct(
        private PersonDataExporter $exporter,
        private PersonEraser $eraser,
    ) {}

    public function export(int $id): JsonResponse
    {
        abort_unless(bouncer()->hasPermission(self::PERMISSION), 403);

        $data = $this->exporter->export($id);

        abort_if($data === null, 404);

        SecurityAuditRecorder::record('privacy.person_exported', 'person', (string) $id, []);

        return response()
            ->json($data, 200, [
                'Content-Disposition' => "attachment; filename=\"person-{$id}-data.json\"",
                'Cache-Control' => 'no-store',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The typed name is the confirmation: it must match the person's current name exactly.
     */
    public function erase(Request $request, int $id): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission(self::PERMISSION), 403);

        $person = DB::table('persons')->where('id', $id)->first(['name', 'erased_at']);

        abort_if($person === null, 404);

        if ($person->erased_at !== null) {
            return back()->with('warning', trans('privacy::app.already-erased'));
        }

        $request->validate(['confirm_name' => 'required|string|max:255']);

        if (trim((string) $request->input('confirm_name')) !== trim((string) $person->name)) {
            return back()->withErrors(['confirm_name' => trans('privacy::app.confirm-mismatch')]);
        }

        $this->eraser->erase($id, auth()->guard('user')->id());

        session()->flash('success', trans('privacy::app.erased-success'));

        return redirect()->route('admin.contacts.persons.view', $id);
    }
}
