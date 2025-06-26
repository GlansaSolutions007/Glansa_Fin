<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Member;
use Illuminate\Support\Facades\Crypt;
use App\Http\Controllers\LoanIssueController;
use App\Models\Savings;
use App\Models\LoanIssue;

class MemberWithdrawalController extends Controller
{
    // Get List
    public function index()
    {
        $companyId = auth()->user()->role_id === 1
        ? cache()->get('superadmin_company_' . auth()->id(), 0)
        : auth()->user()->company_id;

        $members = Member::where('company_id', $companyId)
            ->where('isactive', 0)
            ->where('issuspended', 0)
            ->orderBy('m_no', 'desc')
            ->with('savings') // eager load savings relation
            ->get()
            ->map(function ($member) {
                // sum openingbal + added + intonopening + intonadded from all savings for this member
                $totalSaving = $member->savings->sum(function ($saving) {
                    return 
                        (float) ($saving->openingbal ?? 0) + 
                        (float) ($saving->added ?? 0) + 
                        (float) ($saving->intonopening ?? 0) + 
                        (float) ($saving->intonadded ?? 0);
                });

                $member->total_saving = $totalSaving;
                $member->m_no_encpt = Crypt::encryptString($member->m_no);
                return $member;
            });
            return response()->json($members);
    }

    // Insert
 

    // Update
    public function update(Request $request, $id)
    {
        $decryptedMNo = Crypt::decryptString($id);
        $member = Member::where('m_no', $decryptedMNo)->first();

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }
        // $member = new Member();
        // $member->fill($request->all());4
        $member->issuspended = $request->issuspended;
        $member->wstatusdate = $request->wstatusdate;
        $member->wstatus = $request->wstatus;
        $member->wreasoncode = $request->wreasoncode;
        $member->wreason = $request->wreason;
        $member->withdrawappby = $request->withdrawappby;
        $member->wapplicantname = $request->wapplicantname;
        $member->relnwith_wapplicant = $request->relnwith_wapplicant;
        $member->isactive = ($request->wstatus === 'Approved') ? 0 : 1;
        $member->reason = $request->reason;

        $member->save();

    if ($request->wstatus === 'Approved') {
        $saving = Savings::where('m_no', $decryptedMNo)->first();

        if ($saving) {
            $withdrawal =
                ($saving->openingbal ?? 0) +
                ($saving->added ?? 0) +
                ($saving->intonopening ?? 0) +
                ($saving->intonadded ?? 0);

            $saving->withdrawal_amount = $withdrawal;
            $saving->save();
        }

        
    }

        return response()->json(['success' => true, 'message' => 'Member data updated', 'data' => $member]);
    }

    public function showMemberDetails($encryptedMNo)
    {
        try {
            $decryptedMNo = Crypt::decryptString($encryptedMNo);

            $member = Member::with(['savings', 'loanissues.statusOption'])
                ->where('m_no', $decryptedMNo)
                ->firstOrFail();

            // Add interest calculation for each loan issue
            $member->loanissues->transform(function ($loan) {
                $roi = $loan->roi ?? 0;
                $pending = $loan->loanpending ?? 0;
                $installments = $loan->remaining_installments ?? 0;

                $loan->calculated_interest = $this->calculateInterest($roi, $pending, $installments);

                return $loan;
            });
             $loanIssueController = new LoanIssueController();
            $member->loanissues->transform(function ($loan) use ($loanIssueController) {
                $loan->late_fee = $loanIssueController->calculateLateFee($loan->last_paid_date ?? null);
                return $loan;
            });

            return response()->json($member);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid or corrupted m_no.' ], 400);
        }
    }

    private function calculateInterest($roi = 0, $pending = 0, $installments = 0)
    {
        $parsedROI = floatval($roi);
        $parsedPending = floatval($pending);
        $parsedInstallments = intval($installments);

        $interest = ($parsedPending * $parsedROI * $parsedInstallments) / (100 * 12);
        return round($interest, 2);
    }

}
