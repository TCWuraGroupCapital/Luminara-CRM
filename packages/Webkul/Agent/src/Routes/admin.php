<?php

use Illuminate\Support\Facades\Route;
use Webkul\Agent\Http\Controllers\Admin\ApprovalController;
use Webkul\Agent\Http\Controllers\Admin\AssistantController;
use Webkul\Agent\Http\Controllers\Admin\BriefingController;
use Webkul\Agent\Http\Controllers\Admin\DeskController;

Route::group([
    'middleware' => ['web', 'admin_locale', 'user'],
    'prefix' => config('app.admin_path', 'admin').'/agent/briefing',
], function (): void {
    Route::get('/', [BriefingController::class, 'index'])->name('admin.agent.briefing.index');
    Route::post('/passports', [BriefingController::class, 'storePassport'])->name('admin.agent.briefing.passports.store');
    Route::post('/passports/{id}/toggle', [BriefingController::class, 'togglePassport'])->whereNumber('id')->name('admin.agent.briefing.passports.toggle');
    Route::post('/playbook', [BriefingController::class, 'updatePlaybook'])->name('admin.agent.briefing.playbook.update');
    Route::post('/intake', [BriefingController::class, 'captureIntake'])->name('admin.agent.briefing.intake');
    Route::post('/calls/{id}/review', [ApprovalController::class, 'review'])->whereNumber('id')->name('admin.agent.briefing.review');
});

Route::group([
    'middleware' => ['web', 'admin_locale', 'user'],
    'prefix' => config('app.admin_path', 'admin').'/agent/desk',
], function (): void {
    Route::get('/', [DeskController::class, 'index'])->name('admin.agent.desk.index');
    Route::get('/data', [DeskController::class, 'data'])->name('admin.agent.desk.data');
});

Route::group([
    'middleware' => ['web', 'admin_locale', 'user'],
    'prefix' => config('app.admin_path', 'admin').'/assistant',
], function (): void {
    Route::get('/', [AssistantController::class, 'index'])->name('admin.agent.assistant.index');
    Route::post('/messages', [AssistantController::class, 'send'])->middleware('throttle:20,1')->name('admin.agent.assistant.send');
    Route::post('/stop', [AssistantController::class, 'stop'])->name('admin.agent.assistant.stop');
    Route::post('/reset', [AssistantController::class, 'reset'])->name('admin.agent.assistant.reset');
});

Route::group([
    'middleware' => ['web', 'admin_locale', 'user'],
    'prefix' => config('app.admin_path', 'admin').'/agent/approvals',
], function (): void {
    Route::get('/', [ApprovalController::class, 'index'])->name('admin.agent.approvals.index');
    Route::post('/{id}/approve', [ApprovalController::class, 'approve'])->whereNumber('id')->name('admin.agent.approvals.approve');
    Route::post('/{id}/reject', [ApprovalController::class, 'reject'])->whereNumber('id')->name('admin.agent.approvals.reject');
    Route::post('/{id}/review', [ApprovalController::class, 'review'])->whereNumber('id')->name('admin.agent.approvals.review');
});
