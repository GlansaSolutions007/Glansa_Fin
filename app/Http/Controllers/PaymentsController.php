<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payments;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use App\Models\LoanIssue;

class PaymentsController extends Controller
{
    public function index(Request $request)
        {
            $perPage = $request->input('per_page', 50); // Default to 50
            $search = $request->input('search');
            $companyId = auth()->user()->role_id === 1
                        ? cache()->get('superadmin_company_' . auth()->id(), 0)
                        : auth()->user()->company_id;

            $query = Payments::query()
            ->with(['user:employeeid,name','member:member_id,m_no']) 
            ->where('company_id', $companyId);

            // Apply search if provided
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('membername', 'like', "%$search%")
                    ->orWhere('remarks', 'like', "%$search%")
                    ->orWhere('srlno', 'like', "%$search%")
                    ->orWhere('mno', 'like', "%$search%");
                });
            }

            $payments = $query->orderBy('payments_id', 'desc')->paginate($perPage);

            return response()->json ($payments );
        }
       public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'nullable|integer',
            'date' => 'required|date',
            'srlno' => 'nullable|string',
            'mno' => 'nullable|string',
            'membername' => 'nullable|string',
            'remarks' => 'nullable|string',
            'towardscode' => 'nullable|integer',
            'towards' => 'nullable|string',
            'modeofpmtcode' => 'nullable|integer',
            'modeofpmtname' => 'nullable|string',
            'amount' => 'required|numeric',
            'chqno' => 'nullable|string',
            'chqamount' => 'nullable|numeric',
            'bankname' => 'nullable|string',
            'brname' => 'nullable|string',
            'acntno' => 'nullable|string',
            'entrydate' => 'nullable|date',
            'entryby' => 'nullable|string',
            'loanacntno' => 'nullable|string',
            'loantypecode' => 'nullable|string',
            'loantypename' => 'nullable|string',
            'accountno' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            \Log::error('Payment validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

            $data = $validator->validated();

            $companyId = auth()->user()->role_id === 1
                        ? cache()->get('superadmin_company_' . auth()->id(), 0)
                        : auth()->user()->company_id;

            $data['company_id'] = $companyId;

            if (!empty($data['mno'])) {
                $data['mno'] = Crypt::decryptString($data['mno']);
            }

            $data['entryby'] = auth()->user()->employeeid;

            $payment = Payments::create($data);

            if (isset($data['towardscode']) && (int)$data['towardscode'] === 6) {
                $loanissue = LoanIssue::where('accountno', $data['accountno'])->first();

                if ($loanissue) {
                    $installments = (int) $loanissue->installments;
                    $loanIssueId = $loanissue->loan_id;
                    $loanpending = $loanissue->issueamount;
                    $issuedDate = Carbon::today();

                    $clearenceDate = $issuedDate->copy()->addMonths($installments)->format('Y-m-d');

                    LoanIssue::where('loan_id', $loanIssueId)
                        ->update([
                            'status' => '40',
                            'clearancedate' => $clearenceDate,
                            'issuedate' => $issuedDate,
                            'loanpending' => $loanpending
                        ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment added successfully',
                        'data' => $payment
                    ]);
                } else {
                    \Log::warning('LoanIssue not found for accountno: ' . $data['accountno']);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment added successfully',
                'data' => $payment
            ]);
        }


}
