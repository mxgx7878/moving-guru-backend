<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PasswordResetController;
use App\Http\Controllers\API\ProfileViewController;
use App\Http\Controllers\API\GrowPostController;
use App\Http\Middleware\IsAdmin;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot', [PasswordResetController::class, 'forgot']);
Route::post('/password/reset', [PasswordResetController::class, 'reset']);
Route::get('grow-posts',       [GrowPostController::class, 'index']);
Route::get('grow-posts/{id}',  [GrowPostController::class, 'show'])->whereNumber('id');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::post('/password/change', [PasswordResetController::class, 'change']);

    // Profile Views
    Route::post('/profile/{userId}/view', [ProfileViewController::class, 'store']);
    Route::get('/profile/views', [ProfileViewController::class, 'index']);
    Route::get('/profile/views/analytics', [ProfileViewController::class, 'analytics']);
    Route::get   ('grow-posts/my',        [GrowPostController::class, 'myPosts']);
    Route::post  ('grow-posts',           [GrowPostController::class, 'store']);
    Route::put   ('grow-posts/{id}',      [GrowPostController::class, 'update']);
    Route::delete('grow-posts/{id}',      [GrowPostController::class, 'destroy']);

});

Route::middleware(['auth:sanctum', IsAdmin::class])->prefix('admin')->group(function () {
    Route::get   ('grow-posts',              [GrowPostController::class, 'adminIndex']);
    Route::patch ('grow-posts/{id}/approve', [GrowPostController::class, 'approve']);
    Route::patch ('grow-posts/{id}/reject',  [GrowPostController::class, 'reject']);
    Route::patch ('grow-posts/{id}/boost',   [GrowPostController::class, 'boost']);
    Route::delete('grow-posts/{id}',         [GrowPostController::class, 'adminDestroy']);
});