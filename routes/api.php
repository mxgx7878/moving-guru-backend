<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PasswordResetController;

Route::post('/register', [AuthController::class,'register']);
Route::post('/login', [AuthController::class,'login']);
Route::post('/password/forgot', [PasswordResetController::class,'forgot']);
Route::post('/password/reset', [PasswordResetController::class,'reset']);
Route::middleware('auth:sanctum')->group(function(){
    Route::post('/logout', [AuthController::class,'logout']);
    Route::get('/me', [AuthController::class,'me']);
    Route::post('/refresh', [AuthController::class,'refresh']);
    Route::patch('/profile', [ProfileController::class,'update']);
});