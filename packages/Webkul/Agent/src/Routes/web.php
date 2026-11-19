<?php

use Illuminate\Support\Facades\Route;
use Webkul\Agent\Http\Controllers\DiscoveryController;

Route::get('/llms.txt', [DiscoveryController::class, 'llmsTxt'])->name('agent.discovery.llms');
Route::get('/.well-known/agent.json', [DiscoveryController::class, 'agentCard'])->name('agent.discovery.card');
