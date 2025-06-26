<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\MainBranch;
use App\Models\Member;
use App\Models\LoanIssue;
use Illuminate\Support\Facades\Crypt;
use App\Models\Savings;
use Illuminate\Support\Carbon;
use DB;
use App\Models\CompanyUser;
use App\Models\User;
use App\Models\CompanyPayment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


class ForGlansaApiController extends Controller
{
    public function getallbranches()
    {
        $branch = MainBranch::where('main_branch_id', '!=', 1)->get();
        return response()->json($branch);
    }

    public function companyPaymentsMonthlyReport(Request $request) {
        $now = Carbon::now();
        $currentYear = $now->year;
        $previousYear = $now->copy()->subYear()->year;

        $companyId = auth()->user()->role_id === 1
            ? cache()->get('superadmin_company_' . auth()->id(), 0)
            : auth()->user()->company_id;

        $data = DB::table('company_payments')
            ->selectRaw('YEAR(created) as year, MONTH(created) as month, SUM(amount) as total_amount')
            ->whereIn(DB::raw('YEAR(created)'), [$currentYear, $previousYear])
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return response()->json($data);
    }

    public function storeProductOwnerData(Request $request) {
           
         try {
             $request->validate([
                'username' => 'required|unique:users,username',
                'email' => 'required|email|unique:users,email',
                // add other validations as needed
            ]);
        $mainBranch = MainBranch::create([
                'name' => $request->company_name,
                'subscription_start' => $request->subscription_start,
                'subscription_end' => $request->subscription_end,
                'subscription_status' => $request->status,
                'company_address' => $request->company_address,
                'mandal' => $request->mandal,
                'dist' => $request->dist,
                'pincode' => $request->pincode,
                'entrydate' => now(),
            ]);

            // 2. Insert into company
            $company = CompanyUser::create([
                'main_branch_id' => $mainBranch->main_branch_id ?? '',
                'name' => $request->company_name,
                'min_saving' => '100',
                'admission_fee' => '150',
                'form_fee' => '5',
                'loan_eligibility' => '12',
                'eligibility_amount' => '3',
                'status' => 1,
                'date' => now(),
            ]);

            // 3. Insert user as Product Owner
            $user = User::create([
                'main_branch_id' => $mainBranch->main_branch_id,
                'company_id' => $company->company_id,
                'name' => $request->name,
                'phonenumber' => $request->phonenumber,
                'email' => $request->email,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'usertype' => 'superadmin',
                'role_id' => 1,
                'doj' => $request->doj !== 'null' ? $request->doj :'',
                'dob' => $request->dob !== 'null' ? $request->dob : '',
                'aadhaarno' => $request->aadhaarno,
                'panno' => $request->panno,
                'hno' => $request->hno,
                'landmark' => $request->landmark,
                'colony' => $request->colony,
                'dist' => $request->dist,
                'mandal' => $request->mandal,
                'pincode' => $request->pincode,
                'status' => 1,
                'entryby' => '',
                'entrydate' => now(),
                'acntno' => $request->acntno,
                'ifsccode' => $request->ifsccode,
                'acntname' => $request->acntname,
                'bankname' => $request->bankname,
            ]);

            if ($request->hasFile('image')) {
                    $filename = $user->id . '.' . $request->image->extension();
                    $request->image->storeAs('public/uploads/employees', $filename);

                    // Update image column
                    $user->image = $filename;
                    $user->save();
            }

             return response()->json(['success' => true, 'message' => 'Product Owner added successfully.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }

    }

    public function getallCopmanyPayment() {
        $payment = CompanyPayment::with('main_branch')->get();;
        return response()->json($payment);
    }

    public function updateSubscription(Request $request, $main_branch_id)
    {
        $branch = MainBranch::findOrFail($main_branch_id);
        $branch->subscription_start = $request->subscription_start;
        $branch->subscription_end = $request->subscription_end;
        $branch->updated = $request->updated;
        $branch->save();

        return response()->json(['success' => true, 'message' => 'Subscription updated successfully']);
    }

    public function PaymentStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'main_branch_id' => 'required|exists:main_branches,id',
            'subscription_start' => 'required|date',
            'subscription_end' => 'required|date|after_or_equal:subscription_start',
            'amount' => 'required|numeric|min:1',
            'subscription_name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payment = CompanyPayment::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Company subscription added successfully.',
            'data' => $payment,
        ]);
    }

    // Update existing company payment
    public function PaymentUpdate(Request $request, $id)
    {
        $payment = CompanyPayment::find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'main_branch_id' => 'required|exists:main_branches,id',
            'subscription_start' => 'required|date',
            'subscription_end' => 'required|date|after_or_equal:subscription_start',
            'amount' => 'required|numeric|min:1',
            'subscription_name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payment->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Company subscription updated successfully.',
            'data' => $payment,
        ]);
    }



}
