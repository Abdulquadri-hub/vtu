<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\ProfileController;


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

    Route::controller(ProfileController::class)->prefix('profile')->group(function () {
        Route::get('', "getProfile");
        Route::post('create', "create");
    });

    Route::controller(UserController::class)->prefix('user')->group(function () {
        Route::get('', "index");
        Route::post('create', "create");
    });
});
