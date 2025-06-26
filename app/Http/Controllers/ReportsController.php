<?php

namespace App\Http\Controllers;

use App\Models\Savings;
use Illuminate\Http\Request;
use App\Models\Receipts;
use App\Models\Payments;
use App\Models\LoanIssue;
use App\Models\Member;
use Exception;
use DB;
use Carbon\Carbon;


class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->input('from_date');  // expected 'YYYY-MM-DD'
        $toDate = $request->input('to_date');
        $companyId = auth()->user()->role_id === 1
                    ? cache()->get('superadmin_company_' . auth()->id(), 0)
                    : auth()->user()->company_id;

        if($request -> input('report_type') == 1)
        {
            try {

                $query = Receipts::select('towards', DB::raw('SUM(amount) as total_amount'))
                ->groupBy('towards')
                ->where('company_id', $companyId);

                if (!empty($fromDate) && !empty($toDate)) {
                    $query->whereBetween('receipt_date', [$fromDate, $toDate]);
                }

                $totalReceiptAmounts = $query->get();

                $query = Payments::select('towards', DB::raw('SUM(amount) as total_amount'))
                    ->groupBy('towards')
                    ->where('company_id', $companyId);

                if (!empty($fromDate) && !empty($toDate)) {
                    $query->whereBetween('date', [$fromDate, $toDate]);
                }

                $totalPaymentAmounts = $query->get();

                return response()->json([
                    'total_receipt_amounts' => $totalReceiptAmounts,
                    'total_payment_amounts' => $totalPaymentAmounts
                ]);

            } catch (Exception $e) {
                return response()->json(['error' => 'An error occurred while processing the request.'], 500);
            }
        }
        if($request -> input('report_type') == 2)
        {
            try {

                $query = Receipts::where('company_id', $companyId);

                if (!empty($fromDate) && !empty($toDate)) {
                    $query->whereBetween('receipt_date', [$fromDate, $toDate]);
                }
                if (!empty($m_no)) {
                    $query->where('m_no', $m_no);
                }
                $receipts = $query->get();
                return response()->json(['data' => $receipts]);


            } catch (Exception $e) {
                return response()->json(['error' => 'An error occurred while processing the request.'], 500);
            }
        }

        if($request -> input('report_type') == 3)
        {
            try {

                $query = Payments::where('company_id', $companyId);;
                if (!empty($fromDate) && !empty($toDate)) {
                    $query->whereBetween('date', [$fromDate, $toDate]);
                }
                if (!empty($m_no)) {
                    $query->where('m_no', $m_no);
                }
                $payments = $query->get();
                return response()->json(['data' => $payments]);


            } catch (Exception $e) {
                return response()->json(['error' => 'An error occurred while processing the request.'], 500);
            }
        }
        if($request -> input('report_type') == 5)
        {
            try {
                $delayThresholdDays = 30;
                $cutoffDate = Carbon::now()->subDays($delayThresholdDays);
                 $query = LoanIssue::whereDate('lastpaiddate', '<', $cutoffDate)
                        ->where('loanissues.company_id', $companyId)
                        ->join('members', 'loanissues.mno', '=', 'members.m_no')
                        ->select('loanissues.*', 'members.name as member_name', 'members.mobile1 as member_phone', 'loanissues.lastpaiddate');

                    // 👉 Add date range filter if both dates are present
                    if (!empty($fromDate) && !empty($toDate)) {
                        $query->whereBetween('loanissues.lastpaiddate', [$fromDate, $toDate]);
                    }

                 $delayedLoans = $query->get()
                ->map(function ($loan) {
                    $daysDelayed = Carbon::parse($loan->lastpaiddate)->diffInDays(Carbon::now());
                    $loan->no_of_months = round($daysDelayed / 30);
                    return $loan;
                });
                            
                            
                return response()->json(['data' => $delayedLoans]);

            }
            catch (Exception $e) {
                return response()->json(['error' => 'An error occurred while processing the request.'], 500);
            }
        }
        if($request -> input('report_type') == 4)
        {
            try {
                $delayThresholdDays = 30;
                $cutoffDate = Carbon::now()->subDays($delayThresholdDays);
                $query = Savings::whereDate('lastpaiddate', '<', $cutoffDate)
                            ->where('savings.company_id', $companyId)
                            ->join('members', 'savings.m_no', '=', 'members.m_no')  // Adjust column names if needed
                            ->select('savings.*', 'members.name as member_name', 'members.mobile1 as member_phone') ;


                if (!empty($fromDate) && !empty($toDate)) {
                    $query->whereBetween('savings.lastpaiddate', [$fromDate, $toDate]);
                    }

                 $delayedSaving = $query ->get()
                            ->map(function ($loan) {
                                $daysDelayed = Carbon::parse($loan->lastpaiddate)->diffInDays(Carbon::now());
                                $loan->no_of_months = round($daysDelayed / 30); // rounded to nearest month
                                return $loan;
                            });
                return response()->json(['data' => $delayedSaving]);

            }
            catch (Exception $e) {
                return response()->json(['error' => 'An error occurred while processing the request.'], 500);
            }
        }

        // // Sum amount from Receipt table
        // $totalReceiptAmount = DB::table('Receipt')
        //     ->whereIn('towardscode', [46, 47, 48, 49])

        //     ->whereBetween('date', [$fromDate, $toDate])
        //     ->sum('amount');

        // // Sum amount from Payments table (toward between 6 and 29)
        // $totalPaymentAmount = DB::table('Payments')
        //     ->whereBetween('toward', [6, 29])
        //     ->whereBetween('date', [$fromDate, $toDate])
        //     ->sum('amount');

        // return response()->json([
        //     'total_receipt_amount' => $totalReceiptAmount,
        //     'total_payment_amount' => $totalPaymentAmount,
        // ]);
    }
}
