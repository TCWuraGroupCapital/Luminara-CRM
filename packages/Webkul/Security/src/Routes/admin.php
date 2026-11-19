<?php

use Illuminate\Support\Facades\Route;
use Webkul\Security\Http\Controllers\AuditLogController;
use Webkul\Security\Http\Controllers\MfaController;
use Webkul\Security\Http\Middleware\AuthenticateAdminSession;

// Self-service personal MFA routes accessible to any active authenticated CRM user regardless of role ACL
Route::group([
    'middleware' => ['web', 'admin_locale', AuthenticateAdminSession::class],
    'prefix' => config('app.admin_path', 'admin').'/security/mfa',
], function () {
    Route::get('/', [MfaController::class, 'setup'])->name('admin.security.mfa.setup');
    Route::post('/enable', [MfaController::class, 'enable'])->middleware('throttle:10,1')->name('admin.security.mfa.enable');
    Route::post('/disable', [MfaController::class, 'disable'])->middleware('throttle:5,1')->name('admin.security.mfa.disable');
    Route::get('/challenge', [MfaController::class, 'challenge'])->name('admin.security.mfa.challenge');
    Route::post('/verify', [MfaController::class, 'verify'])->middleware('throttle:5,1')->name('admin.security.mfa.verify');
});

// Privileged audit log explorer gated by Bouncer ('user') + settings.other_settings.security_audit ACL
Route::group([
    'middleware' => ['web', 'admin_locale', 'user'],
    'prefix' => config('app.admin_path', 'admin').'/security',
], function () {
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('admin.security.audit_logs.index');
});
