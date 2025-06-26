<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Receipts;
use Illuminate\Support\Facades\Crypt;
use Exception;
use App\Models\Savings;
use App\Models\LoanIssue;
use Carbon\Carbon;
use DB;
use App\Models\LoanLateFee;
use App\Models\Member;

class ReceiptController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        // $companyId = auth()->user()->company_id;
        $companyId = auth()->user()->role_id === 1
                    ? cache()->get('superadmin_company_' . auth()->id(), 0)
                    : auth()->user()->company_id;

        $query = Receipts::with(['user:employeeid,name','member:member_id,m_no']) 
        ->where('company_id', $companyId);

            if ($request->has('towardscode')) {
                $towardscode = $request->input('towardscode');
                
                if ($towardscode == '46') {
                    // If 'towards' is 'Monthly Savings', filter for Monthly Savings OR Form Sale OR Admission Fee
                    $query->whereIn('towardscode', ['46', '48', '49']);
                } elseif ($towardscode == '47') {
                    // If 'towards' is 'Loan Repayment', filter only by 'Loan Repayment'
                    $query->whereIn('towardscode', ['47']);
                }
            }
            
        if ($request->has('search') && $request->input('search') != '') {
            $search = $request->input('search');

            // Apply the search to multiple fields
            $query->where(function ($query) use ($search) {
                $query->where('membername', 'like', '%' . $search . '%')
                      ->orWhere('m_no', 'like', '%' . $search . '%')
                      ->orWhere('receipt_date', 'like', '%' . $search . '%')
                      ->orWhere('towards', 'like', '%' . $search . '%')
                      ->orWhere('member_id', 'like', '%' . $search . '%'); // <-- Added line
            });
        }

        $receipts = $query->orderBy('receipts_id', 'desc')->paginate($perPage);

        return response()->json($receipts);
    }


public function store(Request $request)
{
    try {
        $data = $request->all();
        $companyId = auth()->user()->role_id === 1
                    ? cache()->get('superadmin_company_' . auth()->id(), 0)
                    : auth()->user()->company_id;

        $receiptDate = Carbon::parse($data['receipt_date']);
        $monthStart = $receiptDate->copy()->startOfMonth();
        $monthEnd = $receiptDate->copy()->endOfMonth();
        $mno = Crypt::decryptString($data['m_no']);

        $repaymentMode = 70; // default to monthly

        if ((int) $data['towardscode'] === 47 && !empty($data['loanacntno'])) {
            $loan = LoanIssue::where('accountno', $data['loanacntno'])->first();

            if ($loan && isset($loan->modeofrepayment)) {
                $repaymentMode = (int) $loan->modeofrepayment;
            }
        }


         // ✅ Check for duplicate payment in the same month
        $query = Receipts::where('company_id', $companyId)
            ->where('m_no', $mno)
            ->where('towardscode', $data['towardscode']);
            if ($data['towardscode'] === 47 && $repaymentMode === 69) {
                // Daily repayment: block duplicate for same day
                $query->whereDate('receipt_date', $receiptDate);
            } else {
                // Monthly (default): block duplicate for same month
                $monthStart = $receiptDate->copy()->startOfMonth();
                $monthEnd = $receiptDate->copy()->endOfMonth();
                $query->whereBetween('receipt_date', [$monthStart, $monthEnd]);
            }
            // ->whereBetween('receipt_date', [$monthStart, $monthEnd])
           $alreadyPaid = $query->exists();

        if ($alreadyPaid) {
            return response()->json([
                'success' => false,
                'error' => 'Payment already made for this member in the selected month.',
            ], status: 201);
        }


         $receipt = DB::transaction(function () use ($companyId, $data) {
            // Lock the last receipt row for update, preventing race condition
        $lastReceipt = Receipts::where('company_id', operator: $companyId)
                ->orderBy('receipts_id', 'desc')
                ->lockForUpdate()
                ->first();

            $nextRcptNumber = $lastReceipt ? $lastReceipt->cvmacs_rcpt_number + 1 : 1000;

        $receipt = new Receipts();
        $receipt->company_id     = $companyId;
        $receipt->receipt_date   = $data['receipt_date'];
        $receipt->m_no           = Crypt::decryptString($data['m_no']);
        $receipt->membername     = $data['membername'];
        $receipt->towards        = $data['towards'];
        $receipt->towardscode    = $data['towardscode'];
        $receipt->trantypename  = $data['trantypename'];
        $receipt->trantypecode   = $data['trantypecode'];
        $receipt->amount         = $data['amount'] ?? 0;
        $receipt->latefee        = $data['latefee'] ?? 0;
        $receipt->interest       = $data['interest'] ?? 0;
        $receipt->totalamount    = $receipt->amount + $receipt->latefee + $receipt->interest;
        $receipt->entrydate      = now();
        // $receipt->entryby        = auth()->user()->id;
        $receipt->cvmacs_rcpt_number = $nextRcptNumber;

        if ((int)$data['towardscode'] === 47) {
            $receipt->loanacntno       = $data['loanacntno'];
            $receipt->loantypecode     = $data['loantypecode'];
            $receipt->loantypename     = $data['loantypename'];
            $receipt->loanpending      = $data['loanpending'];
            $receipt->clearancedate    = $data['clearancedate'];
            $receipt->issueamount      = $data['issueamount'];
            $receipt->roi              = $data['roi'];
            $receipt->modeofrepayment  = $data['modeofrepayment'] ?? 70;
        }

        if (!empty($data['chqno'])) {
            $receipt->chqno     = $data['chqno'];
            $receipt->chqdate   = $data['chqdate'];
            $receipt->chqamount = $data['chqamount'];
            $receipt->bankname  = $data['bankname'];
            $receipt->brname    = $data['brname'];
            $receipt->ifsccode  = $data['ifsccode'];
        }
        $receipt->entryby = auth()->user()->employeeid;
        $receipt->lastpaiddate = $data['lastpaiddate'] ?? now();

        $receipt->save();

        if ((int)$data['towardscode'] === 46) {
            $mno = $receipt->m_no;

            $savings = Savings::where('m_no', $mno)->first();

            if ($savings) {
                // Update existing saving balance
                $savings->added +=  $receipt->amount;
                $savings->save();
            } else {
                // Create new savings record
                Savings::create([
                    'company_id' => $companyId,
                    'm_no' => $mno,
                    'mname' => $receipt->membername,
                    'added' => $receipt->amount,
                    'lastpaiddate' => $receipt->receipt_date
                ]);
            }
        }

         if ((int)$data['towardscode'] === 47) {
            $mno = $receipt->m_no;


          

            $loanissue = LoanIssue::where('accountno', $receipt ->loanacntno)->first();

            

            if ($loanissue) {
                // Update existing saving balance
                $loanissue->loanpending -=  $receipt->amount;
                $loanissue->lastpaiddate = $receipt->receipt_date;  
                $loanissue->remaining_installments -= 1;
                if(!empty($data['loanclear']) && $data['loanclear'] == 1)
                {
                    $loanissue->status = 43;
                    $loanissue->loanpending = 0;
                }
                if($loanissue->loanpending <= 0)
                {
                    $loanissue->status = 43;
                    $loanissue->loanpending = 0;
                }
                $loanissue->save();

            } 
        }
        // Fetch member_id based on m_no
        $member = Member::where('m_no', $mno)->first();
        $memberId = $member ? $member->member_id : null;
        $receipt->member_id = $memberId;
        return $receipt;
    });

        return response()->json([
            'success' => true,
            'message' => 'Receipt saved successfully.',
            'receipt' => $receipt,
        ], 201);
        

    } catch (Exception $e) {
        // Log the error for debugging
        \Log::error('Receipt store failed: ' . $e->getMessage());

        return response()->json([
            'error' => 'Failed to save receipt.',
            'details' => $e->getMessage()
        ], 500);
    }
}


// 

// function calculateLateFee($lastPaidDate)
// {
//     $lastPaid = Carbon::parse($lastPaidDate);
//     $now = Carbon::now();

//     // Get latest late fee amount
//     $lateFeeRow = LoanLateFee::orderByDesc('updated_on')->first();
//     $feePerMonth = $lateFeeRow ? $lateFeeRow->late_fee : 5; // fallback to 5 if not found

//     $monthsDifference = $lastPaid->diffInMonths($now);
//     $dayOfMonth = $now->day;

//     if ($dayOfMonth >= 16 && $lastPaid->lessThan($now->copy()->startOfMonth())) {
//         $monthsDifference += 1;
//     }

//     $lateFee = $monthsDifference > 0 ? $monthsDifference * $feePerMonth : 0;

//     return $lateFee;
// }


        public function getLoanInstallmentDetails(Request $request)
        {
            $mno = $request->input('mno');
            $loanacntno = $request->input('loanacntno');

            $loan = LoanIssue::where('mno', $mno)
                ->where('accountno', $loanacntno)
                ->first();

            if (!$loan) {
                return response()->json(['success' => false, 'message' => 'Loan not found'], 404);
            }

            $issueAmount = (float)$loan->issueamount;
            $roi = (float)$loan->roi;
            $months = (int)$loan->installments;
            $monthlyRate = $roi / 12 / 100;

            $receipts = Receipts::where('towardscode', 47)
                ->where('m_no', $mno)
                ->where('loanacntno', $loanacntno)
                ->orderBy('receipt_date', 'asc')
                ->get();

            $data = [];
            $balance = $issueAmount;
            $monthCount = 1;

            foreach ($receipts as $receipt) {
                $interest = $receipt->interest;
                $paidamount = round($receipt->amount + $interest, 2);
                $closingBalance = round($balance - $receipt->amount+ $interest, 2);

                $data[] = [
                    'month' => $monthCount++,
                    'installmentdate' => $receipt->receipt_date,
                    'Openingbal' => $balance,
                    'interest' => $interest,
                    'instamount' => $receipt->amount,
                    'totalpayment' => $paidamount,
                    'closingbalance' => $closingBalance,
                ];

                $balance = $closingBalance;
            }

            // Manual Pagination
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 10);
            $offset = ($page - 1) * $perPage;

            $paginatedData = array_slice($data, $offset, $perPage);
            $total = count($data);

            return response()->json([
                'success' => true,
                'loanacntno' => $loanacntno,
                'member_no' => $mno,
                'schedule' => $paginatedData,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ]);
        }

            public function destroy($id)
            {
                $receipt = Receipts::findOrFail($id);

                if ($receipt->towardscode == 46) {
                    // Deduct amount from savings
                    $saving = Savings::where('m_no', $receipt->m_no)->first();
                    if ($saving) {
                        $saving->added -= $receipt->amount;
                        $saving->save();
                    }
                }

                if ($receipt->towardscode == 47) {
                    // Update loan pending amount and last paid date
                    $loan = LoanIssue::where('mno', $receipt->m_no)
                        ->where('accountno', $receipt->loanacntno)
                        ->first();
                    if ($loan) {

                         if ($loan->status == 43) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Cannot delete receipt. Please check with administration. Loan is already closed.',
                                ], 403);
                            }
                            
                        $loan->loanpending += $receipt->amount;
                        $loan->lastpaiddate = $receipt->receipt_date;
                        $loan->save();
                    }
                }

                $receipt->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Receipt deleted successfully.',
                    'towardscode' =>  $loan
                ]);
            }


}
