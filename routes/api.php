<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);
    Route::post('/google', [App\Http\Controllers\AuthController::class, 'google']);
    Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Class routes
    Route::post('/classes', [App\Http\Controllers\ClassController::class, 'store']);
    Route::get('/classes', [App\Http\Controllers\ClassController::class, 'index']);
    Route::post('/classes/join', [App\Http\Controllers\ClassController::class, 'join']);
    Route::get('/classes/{classroom}', [App\Http\Controllers\ClassController::class, 'show']);
    Route::get('/classes/{classroom}/members', [App\Http\Controllers\ClassController::class, 'getMembers']);
    Route::delete('/classes/{classroom}/members/{userId}', [App\Http\Controllers\ClassController::class, 'removeMember']);

    // Assignment routes (classroom-scoped)
    Route::post('/classrooms/{classroom}/assignments', [App\Http\Controllers\AssignmentController::class, 'store']);
    Route::get('/classrooms/{classroom}/assignments', [App\Http\Controllers\AssignmentController::class, 'index']);

    // Assignment routes (assignment-scoped)
    Route::get('/assignments/{assignment}', [App\Http\Controllers\AssignmentController::class, 'show']);
    Route::post('/assignments/{assignment}/submit', [App\Http\Controllers\AssignmentController::class, 'submit']);
    Route::get('/assignments/{assignment}/submissions', [App\Http\Controllers\AssignmentController::class, 'submissions']);
});