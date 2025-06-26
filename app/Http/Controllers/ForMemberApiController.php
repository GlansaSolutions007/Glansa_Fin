<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\Receipts;
use App\Models\Member;
use App\Models\LoanIssue;
use Illuminate\Support\Facades\Crypt;
use App\Models\Savings;
use Illuminate\Support\Carbon;
use DB;
use App\Models\CompanyUser;
// use Exception;


class ForMemberApiController extends Controller
{
    //
    public function memberreceipt(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        // $companyId = auth()->user()->company_id;
        $m_no = auth()->user()->m_no;

        $query = Receipts::with(['user:employeeid,name','member:member_id,m_no']) // eager load user with only ID and name
                ->where('m_no', $m_no);

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
                      ->orWhere('member_id', 'like', '%' . $search . '%')
                      ->orWhere('receipt_date', 'like', '%' . $search . '%')
                      ->orWhere('towards', 'like', '%' . $search . '%')
                      ->orWhere('member_id', 'like', '%' . $search . '%'); // <-- Added line
            });
        }

        $receipts = $query->orderBy('receipts_id', 'desc')->paginate($perPage);

        return response()->json($receipts);
    }

    public function memberloans(Request $request)
    {
         $m_no = auth()->user()->m_no;
        $perPage = $request->input('per_page', 50);
            $companyId = auth()->user()->role_id === 1
                        ? cache()->get('superadmin_company_' . auth()->id(), 0)
                        : auth()->user()->company_id;
            $query = LoanIssue::with(['member:member_id,m_no'])
                    ->where('company_id', $companyId)
                                        ->where('mno', $m_no);
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

    public function checkLoanEligibility($m_no)
    {

        $companyId = auth()->user()->role_id === 1
                    ? cache()->get('superadmin_company_' . auth()->id(), 0)
                    : auth()->user()->company_id;
        $m_no = Crypt::decryptString($m_no);

        $members = Member::where('company_id', $companyId)
        ->orderBy('m_no', 'desc')
        ->select('m_no','name', 'doj' ,'acntno' ,'bankname' , 'ifsccode' ,'acntname') 
        ->where('m_no', $m_no)
        ->get()
        ->map(function ($member) {
            $member->m_no_encpt = Crypt::encryptString($member->m_no);

            // Calculate months since DOJ
             if ($member->doj) {
                $doj = Carbon::parse($member->doj);
                $days = $doj->diffInDays(Carbon::now());
                $member->months_since_join = round($days / 30); // Roughly 30 days per month
            } else {
                $member->months_since_join = null;
            }

            $companyId = auth()->user()->role_id === 1
                        ? cache()->get('superadmin_company_' . auth()->id(), 0)
                        : auth()->user()->company_id;

            //get eligibility_amount form company table
            $company = CompanyUser::where('company_id', $companyId)->first();


            // Loan eligibility check
            $loanIssues = LoanIssue::where('mno', $member->m_no)->get();
            $loanCleared = $loanIssues->every(fn($loan) => $loan->status == 43);
            $member->eligible = $loanCleared;
            // $member->message = $loanCleared ? 'New loan eligible' : 'Not eligible for a new loan, loan(s) not cleared';

            // Get latest saving balance (if available)
            $saving = Savings::where('m_no', $member->m_no)->orderBy('savings_id', 'desc')->first();
            $member->openingbal = $saving ? $saving->openingbal+$saving->added : 0;

            $member->eligibleamt = $saving ? (float) ($saving->openingbal+$saving->added) * $company->eligibility_amount : 0; // Assuming savingsamt is in percentage) : 0;

            $instal = round($member->eligibleamt / 1000);
            if ($instal < 5) {
                $instal = 5;
            } elseif ($instal > 50) {
                $instal = 50;
            }

            $member->eligibleinstallments = $instal;

            if (!$loanCleared) {
                $member->message = 'Not eligible, previous loan(s) not cleared.';
            } 
            elseif ($member->months_since_join < 12) {
                // if ($member->months_since_join < 12) {
                    $member->eligible = false;
                    $member->message = 'Not eligible, membership duration less than 12 months.';
                // } 
                // else{
                //     $member->eligible = false;
                //     $member->message = 'Not eligible, saving balance is below 1200.';
                // }
                
            } else {
                $member->eligible = true;
                $member->message = 'New loan eligible';
            }
            
            return $member;
        });

    return response()->json($members);
    }

    public function memberloanrequest(Request $request)
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
            $loanissue->mno           = $data['m_no'];
            $loanissue->mname     = $data['name'];
            $loanissue->typecode        = $data['typecode'];
            $loanissue->typename    = $data['typename'];
            $loanissue->purposecode    = $data['purposecode'];
            $loanissue->purpose    = $data['purpose'];

            $loanissue->modeofrepaymentcode   = 70;
            $loanissue->modeofrepayment   = "Monthly";

            $loanissue->mshipmonths   = $data['months_since_join'];
            $loanissue->roi   = '1.5';
            $loanissue->totalsavingamt   = $data['openingbal'];
            $loanissue->status   = 44;
            $loanissue->appdate   = now();
            $loanissue->issuedate   = $data['issuedate'] ?? null;
            $loanissue->accountno      = $newAccountNo;

            $loanissue->entrydate      = now();
            $loanissue->entryby        = 0;
            $loanissue->remaining_installments     = 0; 
            $loanissue->eligibleamt     = $data['eligibleamt'];
            $loanissue->eligibleinstallments     = $data['eligibleinstallments'];


            // $clearancedate = $issuedate->copy()->addMonths($installments);
            // $loanissue->clearancedate = $clearancedate->toDateString(); // or ->format('Y-m-d')

            $loanissue->save();

                return response()->json(['success' => true, 'message' => 'Loan Issue created successfully', 'loanIssue' => $loanissue], 201);
             });
        } catch (Exception $e) {
        // Log the error for debugging
        // \Log::error('Receipt store failed: ' . $e->getMessage());

        return response()->json([
            'error' => 'Failed to save receipt.',
            'details' =>  $e->getMessage(),
        ], 500);
    }


    }

}
