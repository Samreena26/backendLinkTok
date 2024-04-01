<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;

Route::post('/register', UserController::class . '@register');
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');