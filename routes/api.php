<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WalletController;


Route::get('/', function() {
    return response()->json([
        "status" => "The api server is ready and working!"
    ]);
});

Route::get("unauthorized", function(){
    return response()->json([
        'success' => false,
        'message' => "Unauthorized",
    ], 401);
})->name('unauthorized');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('fund', [WalletController::class, "initiateFunding"]);
});

Route::controller(AuthController::class)->group(function(){
    Route::post('register', 'register');
    Route::post('verify-email', 'verifyEmail');
    Route::post('login', 'login');
    Route::post('logout', 'logout');
    Route::get('forgot-password/{email}', 'forgotPassword');
    Route::post('reset-password', 'resetPassword');
    Route::post('verify-user-pin', 'verifyUserPin')->middleware('auth:sanctum');
});


Route::middleware(['auth:sanctum'])->group(function () {

    Route::controller(WalletController::class)->prefix('wallet')->group(function() {
        Route::post('fund', 'initiateFunding');
        Route::get('verify', 'handleCallback');
        Route::get('balance', 'getBalance');
        Route::get('transactions', 'getTransactions');
    });
});
