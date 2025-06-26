<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Savings;
use App\Models\Receipts;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use App\Models\IntProcessOnSavings;



class IntersetController extends Controller
{
        public function index()
        {
            // Get all processed records, latest first
            $records = IntProcessOnSavings::orderBy('created_at', 'desc')->paginate(15);

            // Pass data to view
            return response()->json($records);
        }

        public function store(Request $request)
        {


            $validated = $request->validate([
                'roi' => 'required|numeric',
                'from_date' => 'required|date',
            ]);

            $roi = $validated['roi'];
            $month = Carbon::parse($validated['from_date'])->format('Y-m');
            $financialYear = Carbon::parse($validated['from_date'])->format('Y') . '-' . Carbon::parse($validated['from_date'])->addYear()->format('Y');

            // ✅ Check if interest already processed for the month
            $alreadyProcessed = DB::table('intprocessonsavings')
                ->where('intonsavings_rcptsmonth', $month)
                ->where('intonsavings_isprocessed', 1)
                ->exists();

            if ($alreadyProcessed) {
                return response()->json([
                    'success' => false,
                    'message' => "Interest for $month has already been processed.",
                ], 409);
            }

            // Begin Transaction
            DB::beginTransaction();

            try {
                // ✅ Bulk update savings interest
                DB::table('savings')
                    ->join('members', 'savings.m_no', '=', 'members.m_no')
                    ->where('members.isactive', 1)
                    ->update([
                        'savings.intonopening' => DB::raw("savings.intonopening + (savings.openingbal * $roi / 100)"),
                        'savings.intonadded' => DB::raw("savings.intonadded + (savings.added * $roi / 100)"),
                    ]);

                $companyId = auth()->user()->role_id === 1
                            ? cache()->get('superadmin_company_' . auth()->id(), 0)
                            : auth()->user()->company_id;

                DB::table('intprocessonsavings')->insert([
                    'company_id' => $companyId ?? 1,
                    'intonsavings_code' => strtoupper(uniqid()),
                    'intonsavings_rcptsmonth' => $month,
                    'intonsavings_processingmonth' => now()->format('Y-m'),
                    'intonsavings_isprocessed' => 1,
                    'intonsavings_monthlyroi' => $roi,
                    'intonsavings_currfinyr' => $financialYear,
                    'intonsavings_status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Interest processed and recorded successfully.',
                ]);

            } catch (\Exception $e) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process interest: ' . $e->getMessage(),
                ], 500);
            }

        }
}