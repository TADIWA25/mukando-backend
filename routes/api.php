<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupInviteController;
use App\Http\Controllers\GroupMemberController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/invite/{code}', [GroupInviteController::class, 'show']);
    Route::post('/groups/join', [GroupInviteController::class, 'join']);
    Route::get('/groups/{id}', [GroupController::class, 'show']);

    Route::get('/groups/{group}/members', [GroupMemberController::class, 'index']);
    Route::patch('/groups/{group}/members/{member}', [GroupMemberController::class, 'promote']);
    Route::delete('/groups/{group}/members/{member}', [GroupMemberController::class, 'destroy']);

    Route::get('/contributions', [ContributionController::class, 'index']);
    Route::post('/contributions', [ContributionController::class, 'store']);
});
