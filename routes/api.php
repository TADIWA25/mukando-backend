<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
<<<<<<< HEAD
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\GroupInviteController;
use App\Http\Controllers\GroupMemberController;

// LoanController is missing, commenting out to prevent errors
// use App\Http\Controllers\Api\LoanController;
// use Illuminate\Support\Facades\Route;
=======

>>>>>>> 694c252 (updated files)
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
<<<<<<< HEAD

// Public routes
Route::post('/register', [AuthController::class,'register']);
Route::post('/login', [AuthController::class,'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function() {
    Route::post('/logout', [AuthController::class,'logout']);

    // Groups
    Route::get('/groups', [GroupController::class,'index']);
    Route::post('/groups', [GroupController::class,'store']);
    Route::post('/groups/join', [GroupInviteController::class, 'join']);
    Route::get('/groups/{id}', [GroupController::class,'show']);
    Route::get('/groups/{group}/members', [GroupMemberController::class, 'index']);
    Route::patch('/groups/{group}/members/{member}', [GroupMemberController::class, 'promote']);
    Route::delete('/groups/{group}/members/{member}', [GroupMemberController::class, 'destroy']);
    // Contributions
    Route::get('/contributions', [ContributionController::class,'index']);
    Route::post('/contributions', [ContributionController::class,'store']);

    // Loans (Controller missing)
    // Route::get('/loans', [LoanController::class,'index']);
    // Route::post('/loans', [LoanController::class,'store']);
    // Route::post('/loans/pay', [LoanController::class,'pay']);

    // Notifications
    Route::get('/notifications', [ContributionController::class,'overdue']);
});
=======
>>>>>>> 694c252 (updated files)
