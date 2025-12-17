<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\MatchingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::post('/login', LoginController::class);
Route::post('/register', RegisterController::class);


Broadcast::routes(['middleware' => ['auth:sanctum']]);


Route::post('/broadcasting/auth', function () {
    if (!request()->has('socket_id') && isset($_POST['socket_id'])) {
        request()->merge($_POST);
    }

    return \Broadcast::auth(request());
})->middleware(['auth:sanctum']);


Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('/logout', LogoutController::class);
    Route::get('/profile', ProfileController::class);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/preview', [OrderController::class, 'preview']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/match-orders', [MatchingController::class, 'matchOrders']);
});
