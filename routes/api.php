<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use PHPUnit\Framework\Attributes\Group;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\DropdownOptionController;
use App\Http\Controllers\LoanIssueController;
use App\Http\Controllers\SavingReceiptController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\MemberWithdrawalController;
use App\Http\Controllers\IntersetController;
use App\Http\Controllers\ForMemberApiController;
use App\Http\Controllers\RazorPaymentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ForGlansaApiController;

// 


// Route::post('/login', [AuthController::class, 'login']); // Public route

// Route::middleware('auth:api')->group(function () {
//     Route::get('/user', [AuthController::class, 'getUser']); // Protected route
//     Route::post('/logout', [AuthController::class, 'logout']);
// });


Route::group(['prefix' => 'auth'], function ($router) {
    Route::post('login', [AuthController::class, 'login']);
    Route::get('companys', [CompanyController::class, 'index']);
    Route::post('/contact-form', [ContactController::class, 'sendMail']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::middleware('auth:api')->group(function(){
    // Route::post('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
     Route::get('dashboard', [DashboardController::class, 'index']);
    Route::apiResource('members', MemberController::class);
    Route::apiResource('addmembers', MemberController::class)->only(['store']);
    // Route::apiResource('members',MemberController::class)->only(['show']);
    Route::post('updatemembers/{id}', [MemberController::class, 'update']);
    Route::get('mno', [MemberController::class, 'mno']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    // receipts
    Route::get('receipts', [ReceiptController::class, 'index']);
    Route::post('addreceipts', [ReceiptController::class, 'store']);


    Route::get('savingreportmonthly', [DashboardController::class, 'savingsMonthlyWiseReport']);

    //payments
    Route::get('payments', [PaymentsController::class, 'index']);
    Route::post('addpayments', [PaymentsController::class, 'store']);

    // loan issues
    Route::get('loan-issues', [LoanIssueController::class, 'index']); 
    Route::get('loan-issues/{loanId}', [LoanIssueController::class, 'getByLoanId']); 
    Route::get('loan-issues/mno/{mno}', [LoanIssueController::class, 'getByMno']); 
   
    Route::post('loan-issues', [LoanIssueController::class, 'store']); 
    Route::put('loan-issues/{loanId}', [LoanIssueController::class, 'update']); 
    Route::put('loan-issues/status/{status}', [LoanIssueController::class, 'updateStatus']); 

    Route::get('dropdown-option/{id}', [DropdownOptionController::class, 'getOptionById']);
    // Route::get('dropdown-options/parent/{parent_id}', [DropdownOptionController::class, 'getOptionsByParentId']);

   

     Route::get('getmembers-withsaving', [MemberController::class, 'getMembersWithSavings']); 
    //  checkLoanStatus
     Route::get('checkLoanStatus/{mno}', [MemberController::class, 'checkLoanStatus']); 
    //  show company users
    Route::get('company-users', [CompanyController::class, 'show']);
        //  show company users
    Route::get('listcompany', [CompanyController::class, 'alllist']);
    Route::post('editcompany/{id}', [CompanyController::class, 'update']);
    Route::post('addcompany', [CompanyController::class, 'store']);


    //reports
    Route::post('reports', [ReportsController::class, 'index']);

    Route::get('/member-details', [MemberWithdrawalController::class, 'index']);
    Route::get('/member-details/{id}', [MemberWithdrawalController::class, 'showMemberDetails']);
    Route::post('/withdrawal/{id}', [MemberWithdrawalController::class, 'update']);

    Route::get('/loan-installment-details', [ReceiptController::class, 'getLoanInstallmentDetails']);

    //interest
    Route::post('/interestrun', [IntersetController::class, 'store']);
    Route::get('/intprocessonsavings', [IntersetController::class, 'index']);

    //switch-company
    Route::post('/switch-company', [CompanyController::class, 'switchCompany']);

    //add user
    Route::post('/add-user', [AuthController::class, 'addUser']);
    Route::post('/edit-user/{id}', [AuthController::class, 'editUser']);
    Route::get('/get-user/{id}', [AuthController::class, 'getuser']);


    Route::get('/users', [AuthController::class, 'allusers']);

    Route::get('/getcompany', [CompanyController::class, 'getcompany']);

    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/addroles', [RoleController::class, 'store']);
    Route::post('/roles/{role}/permissions', [RoleController::class, 'assignPermissions']);
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::post('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/receiptdelete/{id}', [ReceiptController::class, 'destroy']);

    //ForGlansaApiController
    Route::get('forglansa-allbranches', [ForGlansaApiController::class, 'getallbranches']);
    Route::get('forglansa-companypayments-monthly', [ForGlansaApiController::class, 'companyPaymentsMonthlyReport']);
    Route::post('forglansa-maincompanyadd', [ForGlansaApiController::class, 'storeProductOwnerData']);
    Route::get('forglansa-allcompanypayments', [ForGlansaApiController::class, 'getallCopmanyPayment']);
    Route::post('/update-main-branch-subscription/{main_branch_id}', [ForGlansaApiController::class, 'updateSubscription']);
    Route::post('/addcompanypayment', [ForGlansaApiController::class, 'PaymentStore']);
    Route::post('/editcompanypayment/{id}', [ForGlansaApiController::class, 'PaymentUpdate']);


});

// Route::middleware(['auth:member'])->group(function () {
//     // Route::post('me', [AuthController::class, 'me']);
//     Route::get('/member-details/{id}', [MemberWithdrawalController::class, 'showMemberDetails']);
//     Route::post('updatemembers/{id}', [MemberController::class, 'update']);
//     Route::apiResource('members',MemberController::class)->only(['show']);
// });

Route::middleware('auth:api,member')->group(function () {
    Route::post('me', [AuthController::class, 'me']);
    Route::apiResource('members', MemberController::class)
         ->only(['show']);
    Route::post('updatemembers/{id}', [MemberController::class, 'update']);
    Route::get('member-details/{id}', [MemberWithdrawalController::class, 'showMemberDetails']);
    Route::get('member-receipts', [ForMemberApiController::class, 'memberreceipt']);

    Route::post('payment/initiate', [RazorPaymentController::class, 'initiatePayment']);
    Route::post('payment/success', [RazorPaymentController::class, 'handlePaymentSuccess']);
    Route::get('member-loan-issues', [ForMemberApiController::class, 'memberloans']); 
    Route::get('/loan-installment-details', [ReceiptController::class, 'getLoanInstallmentDetails']);
    Route::get('/check-loan-eligibility/{mno}', [ForMemberApiController::class, 'checkLoanEligibility']);
    Route::get('dropdown-options/parent/{parent_id}', [DropdownOptionController::class, 'getOptionsByParentId']);
    // memberloanissue
    Route::post('memberloanrequest', [ForMemberApiController::class, 'memberloanrequest']);
     Route::get('loan-issues/mno/{mno}/status/{status}', [LoanIssueController::class, 'getByMnoAndStatus']); 
});