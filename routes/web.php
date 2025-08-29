<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('/debug', function (Request $request) {
        return [
            'headers' => $request->headers->all(),
            'scheme' => $request->getScheme(),
            'client_ip' => $request->getClientIp(),
            'trusted_proxies' => $request->getTrustedProxies(),
            'login' => route('login'),
            'is_secure' => $request->isSecure(),
            'server_https' => $_SERVER['HTTPS'] ?? 'not set',
            'url_force_scheme' => config('app.url'),
        ];
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/sites.php';
