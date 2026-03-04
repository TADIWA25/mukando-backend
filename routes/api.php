<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContributionController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\LoanApplicationController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\ContributionController as WebContributionController;
use App\Http\Controllers\GroupInviteController;
use App\Http\Controllers\GroupMemberController;
use App\Http\Controllers\LoanController as WebLoanController;
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
    // new endpoint used by frontend toggle control to mark contributions
    Route::put('/groups/{group}/members/{member}', [GroupMemberController::class, 'updatePayment']);
    Route::patch('/groups/{group}/members/{member}', [GroupMemberController::class, 'promote']);
    Route::delete('/groups/{group}/members/{member}', [GroupMemberController::class, 'destroy']);

    // Loan APIs - similar to how contributions are tracked
    Route::get('/groups/{group}/loans', [LoanController::class, 'index']);
    Route::post('/groups/{group}/loans', [LoanController::class, 'store']);
    // Loan applications from frontend (stored in loan_applications table).
    Route::post('/groups/{group}/loan-applications', [LoanApplicationController::class, 'store']);
    Route::get('/groups/{group}/loans/{loan}', [LoanController::class, 'show']);
    Route::patch('/groups/{group}/loans/{loan}', [LoanController::class, 'update']);
    Route::delete('/groups/{group}/loans/{loan}', [LoanController::class, 'destroy']);
    Route::post('/groups/{group}/loans/{loan}/payment', [LoanController::class, 'recordPayment']);

    // My loans - get all loans for current user across groups
    Route::get('/my-loans', [LoanController::class, 'myLoans']);

    // Legacy web routes (keep for backwards compatibility)
    Route::put('/groups/{group}/loans/{loan}', [WebLoanController::class, 'updatePayment']);

    Route::get('/contributions', [ContributionController::class, 'index']);
    Route::post('/contributions', [ContributionController::class, 'store']);
});
