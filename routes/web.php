<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name'    => 'Mukuba Motors API',
        'version' => '1.0.0',
        'status'  => 'running',
    ]);
});
