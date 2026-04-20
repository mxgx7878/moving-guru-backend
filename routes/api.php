<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PasswordResetController;
use App\Http\Controllers\API\ProfileViewController;
use App\Http\Controllers\API\GrowPostController;
use App\Http\Controllers\API\JobListingController;

use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsStudio;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot', [PasswordResetController::class, 'forgot']);
Route::post('/password/reset', [PasswordResetController::class, 'reset']);
Route::get('grow-posts',       [GrowPostController::class, 'index']);
Route::get('grow-posts/{id}',  [GrowPostController::class, 'show'])->whereNumber('id');
Route::get('jobs',            [JobListingController::class, 'index']);
Route::get('jobs/{id}',       [JobListingController::class, 'show'])->whereNumber('id');

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
    // Grow Posts (instructor-facing)
    Route::get   ('grow-posts/my',        [GrowPostController::class, 'myPosts']);
    Route::post  ('grow-posts',           [GrowPostController::class, 'store']);
    Route::put   ('grow-posts/{id}',      [GrowPostController::class, 'update']);
    Route::delete('grow-posts/{id}',      [GrowPostController::class, 'destroy']);
    //JOB LISTINGS & APPLICATIONS
    Route::get('jobs/mine',       [JobListingController::class, 'mine'])
         ->middleware(IsStudio::class);
    Route::post  ('jobs/{id}/apply',   [JobListingController::class, 'apply'])->whereNumber('id');
    Route::get   ('applications/mine', [JobListingController::class, 'myApplications']);
    Route::delete('applications/{id}', [JobListingController::class, 'withdraw'])->whereNumber('id');
});

Route::middleware(['auth:sanctum', IsStudio::class])->group(function () {
    Route::post  ('jobs',                    [JobListingController::class, 'store']);
    Route::patch ('jobs/{id}',               [JobListingController::class, 'update'])->whereNumber('id');
    Route::delete('jobs/{id}',               [JobListingController::class, 'destroy'])->whereNumber('id');
    Route::get   ('jobs/{id}/applicants',    [JobListingController::class, 'applicants'])->whereNumber('id');
    Route::patch ('applications/{id}/status',[JobListingController::class, 'updateApplicationStatus'])->whereNumber('id');
});

Route::middleware(['auth:sanctum', IsAdmin::class])->prefix('admin')->group(function () {
    Route::get   ('grow-posts',              [GrowPostController::class, 'adminIndex']);
    Route::patch ('grow-posts/{id}/approve', [GrowPostController::class, 'approve']);
    Route::patch ('grow-posts/{id}/reject',  [GrowPostController::class, 'reject']);
    Route::patch ('grow-posts/{id}/boost',   [GrowPostController::class, 'boost']);
    Route::delete('grow-posts/{id}',         [GrowPostController::class, 'adminDestroy']);
});