<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\MainBranch;


class DashboardController extends Controller
{
    public function index(Request $request)
    {

        // $companyId = auth()->user()->company_id;
        $companyId = auth()->user()->role_id === 1
        ? cache()->get('superadmin_company_' . auth()->id(), 0)
        : auth()->user()->company_id;

        $receiptDate = Carbon::now(); // current date

        $data = DB::table('company as A')
        ->selectSub(function ($query) use ($companyId) {
            $query->from('members')
                ->selectRaw('COUNT(DISTINCT m_no)')
                ->where('company_id', $companyId)
                ->where('isactive', 1)
                ->where('issuspended', 0);
        }, 'total_members')
        ->selectSub(function ($query) use ($companyId, $receiptDate) {
            $query->from('receipts as r')
                ->join('members as m', 'r.m_no', '=', 'm.m_no')
                ->selectRaw('COALESCE(SUM(r.amount), 0)')
                ->where('r.company_id', $companyId)
                ->where('m.isactive', 1)
                ->where('r.towardscode', '46') // Monthly Savings
                ->whereDate('r.receipt_date', $receiptDate);
        }, 'monthly_savings')
        ->selectSub(function ($query) use ($companyId, $receiptDate) {
            $query->from('receipts as r')
                ->join('members as m', 'r.m_no', '=', 'm.m_no')
                ->selectRaw('COALESCE(SUM(r.amount), 0)')
                ->where('r.company_id', $companyId)
                ->where('m.isactive', 1)
                ->where('r.towardscode', '47') // Loan Repayment
                ->whereDate('r.receipt_date', $receiptDate);
        }, 'loan_repayment')
        ->selectSub(function ($query) use ($companyId, $receiptDate) {
            $query->from('receipts as r')
                ->join('members as m', 'r.m_no', '=', 'm.m_no')
                ->selectRaw('COALESCE(SUM(r.amount), 0)')
                ->where('r.company_id', $companyId)
                ->where('m.isactive', 1)
                ->where('r.towardscode', '49') // Form Sales
                ->whereDate('r.receipt_date', $receiptDate);
        }, 'form_sales')
        ->selectSub(function ($query) use ($companyId, $receiptDate) {
            $query->from('receipts as r')
                ->join('members as m', 'r.m_no', '=', 'm.m_no')
                ->selectRaw('COALESCE(SUM(r.amount), 0)')
                ->where('r.company_id', $companyId)
                ->where('m.isactive', 1)
                ->where('r.towardscode', '48') // Admission Fee
                ->whereDate('r.receipt_date', $receiptDate);
        }, 'admission_fee')

         ->selectSub(function ($query) use ($companyId, $receiptDate) {
            $query->from('receipts as r')
                ->join('members as m', 'r.m_no', '=', 'm.m_no')
                ->selectRaw('COALESCE(SUM(r.latefee), 0)')
                ->where('r.company_id', $companyId)
                ->where('m.isactive', 1)
                ->whereDate('r.receipt_date', $receiptDate);
        }, 'late_fee')
        
        ->where('A.company_id', $companyId)
        ->first();

        $branch = MainBranch::where('main_branch_id', auth()->user()->main_branch_id)->first();

        $data->name = $branch->name;
        $data->subscription_start = $branch->subscription_start;
        $data->subscription_end = $branch->subscription_end;
        $data->subscription_status = $branch->subscription_status;

        return response()->json($data);


    }

    public function savingsMonthlyWiseReport(Request $request) {
            $now = Carbon::now(); // current date
            $currentYear = $now->year;
            $previousYear = $now->subYear()->year;
            $companyId = auth()->user()->role_id === 1
                    ? cache()->get('superadmin_company_' . auth()->id(), 0)
                    : auth()->user()->company_id;

            $data = DB::table('receipts')
                ->join('members', 'receipts.m_no', '=', 'members.m_no')  // adjust column names if different
                ->selectRaw('YEAR(receipt_date) as year, MONTH(receipt_date) as month, SUM(amount) as total_amount')
                ->where('receipts.company_id', $companyId)
                ->where('members.isactive', 1)  
                         // filter active members only
                ->where('towardscode', '46')
                ->whereIn(DB::raw('YEAR(receipt_date)'), [$currentYear, $previousYear])
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get();

             return response()->json($data );

    }
}
