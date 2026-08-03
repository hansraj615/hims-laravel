<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'hims-backend',
        'message' => 'HIMS backend exposes JSON APIs under /api/v1.',
    ]);
});
