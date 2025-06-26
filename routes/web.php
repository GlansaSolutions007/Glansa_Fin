<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});


// Route::post('/login', [AuthController::class, 'login']);
// Route::middleware('auth')->group(function () {
//     Route::get('/user', [AuthController::class, 'me']);  
//     Route::post('/logout', [AuthController::class, 'logout']); 
//     Route::apiResource('members', MemberController::class);
//     Route::apiResource('addmembers', MemberController::class)->only(['store']);
//     Route::apiResource('members',MemberController::class)->only(['show']);
//     Route::get('/savingreceipts', [SavingReceiptController::class, 'index']);
//     Route::get('/dashboard', [DashboardController::class, 'index']);
//     Route::get('/savingreportmonthly', [DashboardController::class, 'savingsMonthlyWiseReport']);
// });




// Route::middleware([CheckJwtToken::class])->apiResource('members', MemberController::class);
// Route::middleware([CheckJwtToken::class])->group(function () {
//     Route::apiResource('members', MemberController::class);
//     Route::apiResource('addmembers', MemberController::class)->only(['store']);
// });

// Route::middleware()->group(function () {
//     Route::get('/members', [MemberController::class, 'index']);
// });

