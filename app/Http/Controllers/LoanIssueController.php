<?php

namespace App\Http\Controllers;

use App\Models\LoanIssue;
use GrahamCampbell\ResultType\Success;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Models\Member;
use App\Models\LoanLateFee;
use Illuminate\Support\Facades\DB;
use App\Models\Savings;
use App\Models\Receipts;


class LoanIssueController extends Controller
{
    // Get all loan issues
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
            $companyId = auth()->user()->role_id === 1
                        ? cache()->get('superadmin_company_' . auth()->id(), 0)
                        : auth()->user()->company_id;
            $query = LoanIssue::with(['member:member_id,m_no'])
                  ->where('company_id', $companyId);
            if ($request->has('search') && $request->input('search') != '') {
                $search = $request->input('search');
                $query->where(function ($query) use ($search) {
                    $query->where('mname', 'like', '%' . $search . '%')
                        ->orWhere('mno', 'like', '%' . $search . '%')
                        ->orWhere('accountno', 'like', '%' . $search . '%');
            });
        }

        $loanIssues = $query->join('dropdown_options', 'loanissues.status', '=', 'dropdown_options.id')
                        ->select('loanissues.*', 'dropdown_options.name as status_name')  // Select all columns from loan_issues and the name from statuses
                        ->orderBy('loan_id', 'desc')
                        ->paginate($perPage);

         // Encrypt loan_id for each item
        $loanIssues->getCollection()->transform(function ($item) {
            $item->loan_id_encpt = Crypt::encrypt($item->loan_id);
            return $item;
        });

        return response()->json($loanIssues);
    }

    // Get loan issue by loan_id
    public function getByLoanId($loanId)
    {
        $loanId = Crypt::decrypt($loanId);
        $loanIssue = LoanIssue::with(['member:member_id,m_no,image'])
                    ->find($loanId); // Find loan issue by loan_id
        if ($loanIssue) {
            // $loanIssue->surity1mno = Crypt::encrypt($loanIssue->surity1mno);

            return response()->json($loanIssue);
        } else {
            return response()->json(['message' => 'Loan not found'], 404);
        }
    }

    // Get loan issue by mno
    public function getByMno($mno, Request $request)
    {
         $mno = Crypt::decryptString($mno);  
         $query = LoanIssue::where('mno', $mno);

        if ($request->input('type') === 'payment') {
            $query->whereIn('status', [39, 49]);
        }

        $loanIssues = $query->get();

        $member = Member::where('m_no', $mno)->first();

        if ($loanIssues->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No account details found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $loanIssues,
            'member' => $member
        ]);
    }

    // Get loan issue by mno and status
    // public function getByMnoAndStatus($mno, $status)
    // {
        
    //     $loanIssues = LoanIssue::join('dropdown_options', 'loanissues.status', '=', 'dropdown_options.id')  
    //                             ->where('loanissues.mno', $mno)  
    //                             ->where('loanissues.status', $status)  
    //                             ->select('loanissues.*', 'dropdown_options.name as status_name')  
    //                             ->get(); 

    //     $loanIssues->transform(function ($issue) {
    //             $issue->late_fee = $this->calculateLateFee($issue->lastpaiddate);
    //             return $issue;
    //         });
        
    //     // Return the loan issues as a JSON response
    //     return response()->json($loanIssues);
    // }

    public function getByMnoAndStatus($mno, $status)
    {
        $loanIssues = LoanIssue::join('dropdown_options', 'loanissues.status', '=', 'dropdown_options.id')  
                                ->where('loanissues.mno', $mno)  
                                ->where('loanissues.status', $status)  
                                ->select('loanissues.*', 'dropdown_options.name as status_name')  
                                ->get(); 

        $loanIssues->transform(function ($issue) {
            $monthlyRate = $issue->roi;
            $installments = $issue->remaining_installments ?? 1;
            $principal = $issue->loanpending;
            $instamount = $issue->instamount;

            // Calculate EMI using standard formula
            $interest =  $this->calculateOneMonthInterest($principal, $monthlyRate);
            $emi = $instamount + $interest;

            // Get total paid so far
            $paidAmount = Receipts::where('loanacntno', $issue->accountno)
                                ->where('towardscode', 47) // loan repayment
                                ->sum('amount');

            $remaining = $principal - ($paidAmount - $issue->interesttotal);

            // Late fee calculation (if needed)
            $issue->late_fee = $this->calculateLateFee($issue->lastpaiddate);

            // Add custom fields
            $issue->emi = round($emi, 2);
            $issue->interest = round($interest, 2);
            $issue->total_paid = $paidAmount;
            $issue->remaining_principal = round($remaining, 2);
            $issue->status_label = $this->getPaymentStatus($emi, $paidAmount, $installments);

            return $issue;
        });

        return response()->json($loanIssues);
    }


    // Insert new loan issue
    public function store(Request $request)
    {
       try {
        return DB::transaction(function () use ($request) {
            $data = $request->all();
            $companyId = auth()->user()->role_id === 1
                    ? cache()->get('superadmin_company_' . auth()->id(), 0)
                    : auth()->user()->company_id;
            $data['company_id'] = $companyId;

            $lastAccountNo = DB::table('loanissues')
                ->select('accountno')
                ->orderByDesc('accountno')
                ->limit(1)
                ->lockForUpdate()
                ->value('accountno');

                 $year = Carbon::now()->format('Y');

            $newAccountNo = $lastAccountNo ? $lastAccountNo + 1 : $year.'1001';
            
            $year = Carbon::now()->format('Y');
            $newAccountNo = $lastAccountNo ? $lastAccountNo + 1 : $year.'0001'; 

            $loanissue = new LoanIssue();
            $loanissue->company_id     = $companyId;
            $loanissue->mno           = Crypt::decryptString($data['mno']);
            $loanissue->mname     = $data['mname'];
            $loanissue->typecode        = $data['typecode'];
            $loanissue->typename    = $data['typename'];
            $loanissue->purposecode    = $data['purposecode'];
            $loanissue->purpose    = $data['purpose'];

            $loanissue->modeofrepaymentcode   = $data['modeofrepaymentcode'];
            $loanissue->modeofrepayment   = $data['modeofrepayment'];

            $loanissue->mshipmonths   = $data['mshipmonths'];
            $loanissue->roi   = $data['roi'];
            $loanissue->totalsavingamt   = $data['totalsavingamt'];
            $loanissue->status   = $data['status'];
            $loanissue->statusname   = $data['statusname'];

            $loanissue->appdate   = $data['appdate'];
            $loanissue->issuedate   = $data['issuedate'];
            $loanissue->accountno      = $newAccountNo;

            // $loanissue->accountno = $this->generateLoanAccountNumber(Crypt::decryptString($data['mno']));

            // $loanissue->clearancedate   = $data['clearancedate'];
            // lastpaiddate null

            $loanissue->lastpaiddate = $data['lastpaiddate'] ?? null;

            $loanissue->issueamount    = $data['issueamount'];
            $loanissue->installments   = $data['installments'];
            $loanissue->remaining_installments   = $data['installments'];

            $loanissue->instamount     = $data['instamount'];
            $loanissue->eligibleinstallments   = $data['eligibleinstallments'];
            $loanissue->eligibleamt    = $data['eligibleamt'];
            $loanissue->loanpending    = $data['issueamount'] ?? 0;
            $loanissue->surity1mno     = $data['surity1mno'] ?? null;
            $loanissue->surity1mname   = $data['surity1mname'] ?? null;
            $loanissue->surity1details = $data['surity1details'] ?? null;
            $loanissue->surity2mno     = $data['surity2mno'] ?? null;
            $loanissue->surity2mname   = $data['surity2mname'] ?? null;
            $loanissue->surity2details = $data['surity2details'] ?? null;
            $loanissue->clearancedate  = $data['clearancedate'] ?? null;


            $loanissue->entrydate      = now();
            $loanissue->entryby        = auth()->user()->id;

            // Calculate clearance date
            $issuedate = Carbon::parse($data['issuedate']);
            $installments = (int) $data['installments'];

            // $clearancedate = $issuedate->copy()->addMonths($installments);
            // $loanissue->clearancedate = $clearancedate->toDateString(); // or ->format('Y-m-d')

            $loanissue->save();


                return response()->json(['success' => true, 'message' => 'Loan Issue created successfully', 'loanIssue' => $loanissue], 201);
             });
        } catch (Exception $e) {
        // Log the error for debugging
        // \Log::error('Receipt store failed: ' . $e->getMessage());

        return response()->json([
            'error' => 'Failed to save loan.',
            'details' => $e->getMessage()
        ], 500);
    }


    }

    // Update a loan issue
    public function update(Request $request, $loanId)
    {
        $loanId = Crypt::decrypt($loanId);
        $loanIssue = LoanIssue::find($loanId);
        if ($loanIssue) {
            $loanIssue->update($request->all()); // Update with new data
            return response()->json(['success' => true, 'message' => 'Loan Issue updated successfully', 'loanIssue' => $loanIssue]);
        } else {
            return response()->json(['message' => 'Loan not found'], 404);
        }
    }

    // Update loan issues based on status
    public function updateStatus(Request $request, $status)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            // Other fields you want to update
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Update loan issues based on status
        $loanIssues = LoanIssue::where('status', $status)->get();
        foreach ($loanIssues as $loanIssue) {
            $loanIssue->update($request->all()); // Update each loan issue
        }

        return response()->json(['message' => 'Loan issues updated successfully']);
    }

    private function generateLoanAccountNumber($mno)
    {
         // Base part of the account number: YEAR + MNO, e.g. 202500123 for member 123 in 2025
        $year = Carbon::now()->format('Y'); // current year
        $mnoPadded = str_pad($mno, 3, '0', STR_PAD_LEFT);
        $baseAccountNo = $year . $mnoPadded; // like 202500123

        // Count existing accounts with this base prefix to determine next sequence
        $existingCount = LoanIssue::where('accountno', 'like', $baseAccountNo . '%')->count();

        $sequence = $existingCount + 1;
        $sequencePadded = str_pad($sequence, 2, '0', STR_PAD_LEFT);

        // Compose full unique account number
        $uniqueAccountNo = $baseAccountNo . $sequencePadded; // e.g. 202500123001

        return $uniqueAccountNo;
    }

    // use Carbon\Carbon;

        public  function calculateLateFee($lastPaidDate)
        {

                // $lastPaid = Carbon::parse($lastPaidDate);
                // $now = Carbon::now();

                // // Get latest late fee amount
                // $lateFeeRow = LoanLateFee::orderByDesc('updated_on')->first();
                // $feePerMonth = $lateFeeRow ? $lateFeeRow->late_fee : 5; // fallback to 5 if not found

                // // $monthsDifference = $lastPaid->diffInMonths($now);
                // $monthsDifference = ($now->year - $lastPaid->year) * 12 + ($now->month - $lastPaid->month)-1;
                // $dayOfMonth = $now->day;

                // if ($dayOfMonth >= 16 && $lastPaid->lessThan($now->copy()->startOfMonth())) {
                //     $monthsDifference += 1;
                // }
                //  $monthsDifference =(int) round($monthsDifference);

                // $lateFee = $monthsDifference > 0 ? $monthsDifference * $feePerMonth : 0;

                // return $lateFee;
            $lastPaid = Carbon::parse($lastPaidDate);
            $now = Carbon::now();

            // Calculate overdue months
            $monthsDifference = ($now->year - $lastPaid->year) * 12 + ($now->month - $lastPaid->month);

            if ($monthsDifference <= 0) {
                return 0; // No overdue
            }

            // Get latest rule set
            $latestUpdatedOn = \DB::table('loan_late_fee')->max('updated_on');
            $feeRules = \DB::table('loan_late_fee')
                ->where('updated_on', $latestUpdatedOn)
                ->orderBy('from_date')
                ->get();

            // Determine today's applicable fee
            $day = $now->day;
            $applicableFee = 0;

            foreach ($feeRules as $rule) {
                if ($day >= $rule->from_date && $day <= $rule->to_date) {
                    $applicableFee = $rule->late_fee;
                    break;
                }
            }

            // Return total fee = months * per-month rule-based fee
            return $monthsDifference * $applicableFee;


        }



       public function calculateOneMonthInterest(float $principal, float $annualROI): float
        {
            $monthlyRate = $annualROI / 12 / 100; // Convert annual % ROI to monthly decimal
            $interest = $principal * $monthlyRate;
            return round($interest, 2);
        }

        public function getPaymentStatus($emi, $paidAmount, $installments)
        {
            $expectedTotal = $emi * $installments;

            if ($paidAmount >= $expectedTotal) {
                return 'Completed';
            } elseif ($paidAmount >= ($expectedTotal - $emi)) {
                return 'Near Completion';
            } elseif ($paidAmount >= ($emi * 0.5)) {
                return 'In Progress';
            } else {
                return 'Low Payment';
            }
        }


}