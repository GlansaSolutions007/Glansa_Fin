<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Member;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\LoanIssue; 
use App\Models\Savings;
use App\Models\CompanyUser;


class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $companyId = auth()->user()->role_id == 1
                    ?  cache()->get('superadmin_company_' . auth()->id(), 0)
                    : auth()->user()->company_id;

        $members = Member::where('company_id', $companyId)
            ->where('isactive', 1)
            // ->where('issuspended', 0)
            ->orderBy('m_no', 'desc')
            ->with('savings') // eager load savings relation
            ->get()
            ->map(function ($member) {
                // sum openingbal + added + intonopening + intonadded from all savings for this member
                $totalSaving = $member->savings->sum(function ($saving) {
                                            return 
                                                number_format(
                            (float) ($saving->openingbal ?? 0) +
                            (float) ($saving->added ?? 0) +
                            (float) ($saving->intonopening ?? 0) +
                            (float) ($saving->intonadded ?? 0),
                            2,
                            '.',
                            ''
                        );
                                        });

                $member->total_saving = $totalSaving;
                $member->m_no_encpt = Crypt::encryptString($member->m_no);
                return $member;
            });
            return response()->json($members);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $data = $request->all(); // Get all request data

        $companyId = auth()->user()->role_id === 1
                    ? cache()->get('superadmin_company_' . auth()->id(), 0)
                    : auth()->user()->company_id;

        $data['company_id'] = $companyId;
        $data['entrydate'] = Carbon::now(); // Add the entrydate field
        $data['entryby'] = auth()->user()->employeeid;

         // ✅ Generate m_no = company_id + last m_no under this company + 1
        $lastMember = Member::where('company_id', $companyId)
                        ->orderByDesc('m_no')
                        ->first();

        if ($lastMember && $lastMember->member_id) {
            // Extract number part by removing company prefix
            $lastNumber = (int) substr($lastMember->member_id, strlen((string)$companyId));
        } else {
            $lastNumber = 0;
        }

        // New m_no
        $newMno = $companyId . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT); // e.g., 3001, 3002
        $data['member_id'] = $newMno;

        $member = Member::create($data);
        
            // Then handle image upload and move
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $extension = $image->getClientOriginalExtension();

                // Generate filename based on m_mno
                $filename = $member->m_no . '.' . $extension;

                // Store the file in 'uploads' directory with new name
                $path = $image->storeAs('uploads/members', $filename, 'public');

                // Generate the URL
                $url = asset('storage/' . $path);

                // Update the member record with image URL
                $member->update([
                    'image' => $filename,
                ]);
            }

        return response()->json([
            'success' => true,
            'message' => 'Member saved successfully',
            'member' => $member,
        ]);

    
    }

    // /**
    //  * Display the specified resource.
    //  */
   public function show(string $id)
    {
        // $companyId = auth()->user()->company_id;
        $companyId = auth()->user()->role_id === 1
        ? cache()->get('superadmin_company_' . auth()->id(), 0)
        : auth()->user()->company_id;

        $m_no = Crypt::decryptString($id);

        $member = Member::where('company_id', $companyId)
            ->where('m_no', $m_no)
            ->orderBy('m_no', 'desc')
            ->first();

        if (!$member) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        // Encrypt member number
        $member->m_no_encpt = Crypt::encryptString($member->m_no);

        // Calculate months since DOJ
        if ($member->doj) {
            $doj = Carbon::parse($member->doj);
            $days = $doj->diffInDays(Carbon::now());
            $member->months_since_join = round($days / 30); // Approximate months
        } else {
            $member->months_since_join = null;
        }

        // Get company eligibility amount
        $company = CompanyUser::where('company_id', $companyId)->first();

        // Loan eligibility check - get last loan issue with status 40
        $loanIssue = LoanIssue::where('mno', $member->m_no)
            ->where('status', 40)
            ->orderBy('loan_id', 'desc') // or use created_at if available
            ->first();

        $member->loanpending = $loanIssue ? $loanIssue->loanpending : null;

        // Get latest saving balance (if available)
        $saving = Savings::where('m_no', $member->m_no)->orderBy('savings_id', 'desc')->first();
        $member->openingbal = $saving ? $saving->openingbal : 0;
         $member->totalsavings =  (float) ($saving->openingbal ?? 0) + 
                        (float) ($saving->added ?? 0) + 
                        (float) ($saving->intonopening ?? 0) + 
                        (float) ($saving->intonadded ?? 0);

        // Calculate eligible amount based on company eligibility percentage
        $member->eligibleamt = $saving && $company
            ? (float) ($saving->openingbal+$saving->added) * $company->eligibility_amount
            : 0;

        // Calculate eligible installments with min 5 and max 50
        $instal = round($member->eligibleamt / 1000);
        if ($instal < 5) {
            $instal = 5;
        } elseif ($instal > 50) {
            $instal = 50;
        }
        $member->eligibleinstallments = $instal;

        return response()->json([
            'success' => true,
            'member' => $member,
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         try {
        $decryptedMNo = Crypt::decryptString($id);
        $member = Member::where('m_no', $decryptedMNo)->first();

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        // Only allow specific fields from request
        $data = $request->only([
            'name',
            'aliasname',
            'surname',
            'designation',
            'doj',
            'dob',
            'occupan',
            'aadhaarno',
            'panno',
            'stayingwith',
            'swname',
            'swoccupan',
            'nomineename',
            'rwnominee',
            'tfamilymembers',
            'femalecnt',
            'malecnt',
            'mobile1',
            'mobile2',
            'landline',
            'refmno',
            'isownresidence',
            'tmphno',
            'tmpcolony',
            'tmpmandal',
            'tmpdist',
            'tmplandmark',
            'tmppin',
            'prmnthno',
            'prmntcolony',
            'prmntmandal',
            'prmntdist',
            'prmntlandmark',
            'prmntpin',
            'acntno',
            'acntname',
            'ifsccode',
            'bankname',
            'issuspended',
            'wstatusdate',
            'wstatus',
            'wreasoncode',
            'wreason',
            'withdrawappby',
            'wapplicantname',
            'relnwith_wapplicant',
            'suritymno',
            'reason',
            'isactive'
        ]);

        // Handle image file if uploaded
         if ($request->hasFile('image')) {
                $image = $request->file('image');
                $extension = $image->getClientOriginalExtension();

                // Generate filename based on m_mno
                $filename = $decryptedMNo . '.' . $extension;

                // Store the file in 'uploads' directory with new name
                $path = $image->storeAs('uploads/members', $filename, 'public');

                // Generate the URL
                $url = asset('storage/' . $path);

                // Update the member record with image URL
                $member->update([
                    'image' => $filename,
                ]);
            }

            $companyId = auth()->user()->role_id === 1
                        ? cache()->get('superadmin_company_' . auth()->id(), 0)
                        : auth()->user()->company_id;
        // Add backend-controlled fields
        $data['company_id'] =  $companyId;
        $data['modifydate'] = Carbon::now();
        $data['modifyby'] = auth()->user()->employeeid;
        $data['wstatusdate'] = ($request->wstatusdate && $request->wstatusdate !== '0000-00-00') 
            ? $request->wstatusdate 
            : null;

        // Avoid null decimal errors by removing null values
        $data = array_filter($data, function ($value) {
            return $value !== null && $value !== 'null' && $value !== '';
        });

        // Perform update
        $updated = Member::where('m_no', $decryptedMNo)->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Member updated successfully',
            'member' => $updated,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error updating member',
            'error' => $e->getMessage()
        ], 500);
    }
       

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function mno(Request $request)
    {
        $companyId = auth()->user()->role_id === 1
        ? cache()->get('superadmin_company_' . auth()->id(), 0)
        : auth()->user()->company_id;

        $members = Member::where('company_id', $companyId)
        ->orderBy('m_no', 'desc')
        ->select('m_no','member_id','image','doj')
        ->where('isactive', 1)
        ->get()->map(function ($member) {
            $member->m_no_encpt = Crypt::encryptString($member->m_no);

            return $member;
        });
        return response()->json($members);
    }

     public function underprocessmno(Request $request)
    {
        // Get company_id for the authenticated user
        $companyId = auth()->user()->role_id === 1
                        ? cache()->get('superadmin_company_' . auth()->id(), 0)
                        : auth()->user()->company_id;

        // Join the 'loanissues' table with 'members' table based on 'm_no' 
        // and filter by status = 44
        $members = Member::where('company_id', $companyId)
            ->orderBy('m_no', 'desc')
            ->join('loanissues', 'loanissues.mno', '=', 'members.m_no')  // Join loanissues table
            ->where('loanissues.status', 44)  // Filter by status = 44
            ->where('members.isactive', 1)
            ->select('members.m_no', 'loanissues.id', 'loanissues.status') // Select relevant fields
            ->get()
            ->map(function ($member) {
                // Encrypt m_no for security
                $member->m_no_encpt = Crypt::encryptString($member->m_no);
                return $member;
            });

        // Return response as JSON
        return response()->json($members);
    }
 // Define relationship to LoanIssue
    // public function getMembersWithSavings()
    // {
    //     // Get the date 12 months ago from today
    //     $twelveMonthsAgo = Carbon::now()->subMonths(12)->toDateString();

    //     // Fetch members who joined more than 12 months ago, have Loan Cleared (status = 43), and have savings
    //     $members = Member::whereDate('doj', '<', $twelveMonthsAgo) // Members who joined more than 12 months ago
    //     ->whereHas('savings', function ($query) {
    //         $query->whereNotNull('saving_bal'); // Ensure the savings have a non-null balance
    //     })
    //     ->get();
    //      return response()->json($members);
    // }

    public function checkLoanStatus($m_no)
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
            elseif ($member->months_since_join < $company->loan_eligibility) {
                $member->eligible = false;
                $member->message = 'Not eligible, membership duration less than ' . $company->loan_eligibility . ' months.';
            } else {
                $member->eligible = true;
                $member->message = 'New loan eligible';
            }
            
            return $member;
        });

    return response()->json($members);
    }
}
