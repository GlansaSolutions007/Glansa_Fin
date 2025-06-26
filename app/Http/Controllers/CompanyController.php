<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CompanyUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;
use App\Models\SavingLateFee;
use App\Models\Receipts;

class CompanyController extends Controller
{
    public function index() {
       $users = CompanyUser::where('status', 1)
                            ->where('main_branch_id', auth('api')->user()->main_branch_id)
                            ->get()->map(function ($user) {
            $user->comapany_id_encpt = Crypt::encryptString($user->comapany_id);
            return $user;
        });
        return response()->json($users);
    }

    public function show() {
        $companyId = auth()->user()->role_id === 1
        ? cache()->get('superadmin_company_' . auth()->id(), 0)
        : auth()->user()->company_id;

        // $companyId = auth()->user()->company_id;

        $users = CompanyUser::where('status', 1)
            ->where('company_id', $companyId)
            ->get()
            ->map(function ($user) {
                $user->comapany_id_encpt = Crypt::encryptString($user->comapany_id);
                return $user;
            });

        return response()->json($users);
    }

    
    public function alllist() {
        $companyId = auth()->user()->main_branch_id;

        $users = CompanyUser::where('main_branch_id', $companyId)
            ->get()
            ->map(function ($user) {
                $user->comapany_id_encpt = Crypt::encryptString($user->comapany_id);
                return $user;
        });

        return response()->json($users);
    }

    public function store(Request $request)
        {
            $data = $request->all();


            $data['main_branch_id'] = auth()->user()->main_branch_id;

            $user = CompanyUser::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Company saved successfully',
                'user' => $user,
            ]);
        }

    public function update(Request $request, $id) {
        $data = $request->all();
        $user = CompanyUser::find($id);
        $user->update($data);
        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully',
            'user' => $user,
        ]);
    }

    public function switchCompany(Request $request)
    {
        if (auth()->user()->role_id === 1) {
            // session(['superadmin_company_id' => $request->company_id]);
            cache()->put('superadmin_company_' . auth()->id(), $request->company_id);
            return response()->json(['success' => true , 'companyid1' =>  cache()->get('superadmin_company_' . auth()->id(), 0)]);
        }
        return response()->json(['error' => 'Unauthorized'], 403);
    }


    // public function getcompany() {
    //     $companyId = auth()->user()->role_id === 1
    //     ? cache()->get('superadmin_company_' . auth()->id(), 0)
    //     : auth()->user()->company_id;
    //     $user = CompanyUser::where('company_id', $companyId)->first();

    //     return response()->json($user);
    // }

    public function getcompany(Request $request)
    {
        $companyId = auth()->user()->role_id === 1
            ? cache()->get('superadmin_company_' . auth()->id(), 0)
            : auth()->user()->company_id;

        $user = CompanyUser::where('company_id', $companyId)->first();

        // ✅ Get M.No from request
        $m_no = Crypt::decryptString($request->input('m_no'));
        $towardscode = $request->input('towardscode');
        $lateFee = 0;
        $lastReceipt = null;
        if($towardscode == '46'){
            $lastReceipt = Receipts::where('company_id', $companyId)
                ->where('m_no', $m_no)
                ->where('towardscode', 46)
                ->orderByDesc('receipt_date')
                ->first();
            if ($lastReceipt) {
                $lateFee = $this->calculateLateFee($lastReceipt->lastpaiddate , $companyId);
            }
        }

        return response()->json([
            'user' => $user,
            'late_fee' => $lateFee,
            'lastpaiddate' => $lastReceipt ? $lastReceipt->lastpaiddate : null
        ]);
    }

   private function calculateLateFee($lastPaidDate , $companyId)
    {
        $now = Carbon::now();
        $lastPaid = Carbon::parse($lastPaidDate);

        // Calculate number of months between last payment and now
        $monthsDifference = ($now->year - $lastPaid->year) * 12 + ($now->month - $lastPaid->month);

        // If same month or future, no fee
        if ($monthsDifference <= 0) {
            return 0;
        }

        // Get latest updated_on set of late fee rules
        // $latestUpdatedOn = \DB::table('saving_late_fee')->max('updated_on');

        // Get rules for that set
        $feeRules = \DB::table('saving_late_fee')
            ->where('company_id', $companyId)
            ->orderBy('from_date')
            ->get();

        // Determine applicable fee by day of the month
        $day = $now->day;
        $applicableFee = 0;

        foreach ($feeRules as $rule) {
            if ($day >= $rule->from_date && $day <= $rule->to_date) {
                $applicableFee = $rule->saving_fee;
                break;
            }
        }

        // Apply only 1 month late fee based on rule
        return $monthsDifference * $applicableFee;
    }

    
}
