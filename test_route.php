<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

Route::middleware('api')->get('/api/test-token', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'cookie' => $request->cookie('auth_token'),
        'bearer' => $request->bearerToken(),
        'all_cookies' => $request->cookies->all()
    ]);
});
