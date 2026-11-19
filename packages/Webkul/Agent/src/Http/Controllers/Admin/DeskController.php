<?php

namespace Webkul\Agent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Agent\Support\AssistantSettings;
use Webkul\Agent\Support\DeskData;

class DeskController extends Controller
{
    public const PERMISSION = 'agent_desk';

    public function __construct(private DeskData $desk) {}

    public function index(): View
    {
        $this->authorizeUser();

        return view('agent::desk.index', [
            'desk' => $this->desk->build(auth()->guard('user')->user()),
            'model' => AssistantSettings::resolve()['model'],
            'assistant' => bouncer()->hasPermission(AssistantController::PERMISSION),
        ]);
    }

    public function data(): JsonResponse
    {
        $this->authorizeUser();

        return new JsonResponse($this->desk->build(auth()->guard('user')->user()));
    }

    private function authorizeUser(): void
    {
        abort_unless(bouncer()->hasPermission(self::PERMISSION), 401, trans('admin::app.errors.unauthorized'));
    }
}
