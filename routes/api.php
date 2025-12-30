<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [\App\Http\Controllers\Api\v1\LoginController::class, 'login'])->middleware('throttle:5,1');

Route::middleware(['jwtauth', 'register.transaction'])->group(function () {
    Route::post('/verify', [\App\Http\Controllers\Api\v1\LoginController::class, 'verify'])->middleware('addNewToken');
    Route::post('/process', [\App\Http\Controllers\Api\v1\RequestController::class, 'resolveRequest']);
});
