<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Razorpay\Api\Api;
use App\Models\Receipts;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use DB;
use App\Models\Savings;
use App\Models\LoanIssue;

class RazorPaymentController extends Controller
{
    public function initiatePayment(Request $request)
    {
        // $request->validate([
        //     'm_no' => 'required|string',
        //     'name' => 'required|string',
        //     'amount' => 'required|numeric|min:1',
        //     'receipt_date' => 'required|date',
        // ]);

        $data = $request->all();

        $companyId = auth()->user()->role_id === 1
            ? cache()->get('superadmin_company_' . auth()->id(), 0)
            : auth()->user()->company_id;

        $receiptDate = Carbon::parse($data['receipt_date']);
        $monthStart = $receiptDate->copy()->startOfMonth();
        $monthEnd = $receiptDate->copy()->endOfMonth();

        $alreadyPaid = Receipts::where('company_id', $companyId)
            ->where('m_no', $data['m_no'])
            ->where('towardscode', $data['towardscode'])
            ->whereBetween('receipt_date', [$monthStart, $monthEnd])
            ->exists();

        if ($alreadyPaid) {
            return response()->json([
                'success' => false,
                'message' => 'Payment already made for this month.',
            ]);
        }

        $api = new Api( config('services.razorpay.key'),  config('services.razorpay.secret'));

        if($data['amount'] > 0) {
            if($data['towardscode'] == 47) {
                $amount = $data['totalamount'];
            }
            else{
                $amount = $data['amount'];
            }
            $orderData = [
                'receipt'         => 'rcptid_' . time(),
                'amount'          =>  $amount * 100,
                'currency'        => 'INR',
                'payment_capture' => 1,
            ];
        }

        // $orderData = [
        //     'receipt'         => 'rcptid_' . time(),
        //     'amount'          =>  * 100,
        //     'currency'        => 'INR',
        //     'payment_capture' => 1,
        // ];

        try {
            $order = $api->order->create($orderData);
            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'amount' => $amount,
            ]);
        } catch (\Exception $e) {
            \Log::error('Razorpay order error: ' . $e->getMessage() . ' - ' .  config('services.razorpay.key'));
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handlePaymentSuccess(Request $request)
    {
        $input = $request->all();

        $api = new Api( config('services.razorpay.key'),  config('services.razorpay.secret'));
        $companyId = auth()->user()->role_id === 1
            ? cache()->get('superadmin_company_' . auth()->id(), 0)
            : auth()->user()->company_id;

        try {
            $attributes = [
                'razorpay_order_id' => $input['razorpay_order_id'],
                'razorpay_payment_id' => $input['razorpay_payment_id'],
                'razorpay_signature' => $input['razorpay_signature'],
            ];

            $api->utility->verifyPaymentSignature($attributes);

          

        $receipt = DB::transaction(function () use ($companyId, $input) {
            // Lock the last receipt row for update, preventing race condition
            $lastReceipt = Receipts::where('company_id', operator: $companyId)
                ->orderBy('receipts_id', 'desc')
                ->lockForUpdate()
                ->first();

            $nextRcptNumber = $lastReceipt ? $lastReceipt->cvmacs_rcpt_number + 1 : 1000;

            $receipt = new Receipts();
            $receipt->company_id = auth()->user()->company_id;
            $receipt->receipt_date = Carbon::parse($input['receipt_date']);
            $receipt->m_no = $input['m_no'];
            $receipt->membername = $input['name'];
            $receipt->towardscode = $input['towardscode'];
            if ((int)$input['towardscode'] === 47) {
                $receipt->loanacntno       = $input['loanacntno'];
                $receipt->loantypecode     = $input['loantypecode'];
                $receipt->loantypename     = $input['loantypename'];
                $receipt->loanpending      = $input['loanpending'];
                $receipt->clearancedate    = $input['clearancedate'];
                $receipt->issueamount      = $input['issueamount'];
                $receipt->roi              = $input['roi'];
                $receipt->modeofrepayment  = $input['modeofrepayment'] ?? 70;
                 $receipt->towards = 'Loan Repayment';
            }
            else{
                 $receipt->towards = 'Monthly Savings';
            }
           
            
            $receipt->trantypename = 'Online Transaction';
            $receipt->trantypecode = 33;
            $receipt->amount         = $input['amount'] ?? 0;
            $receipt->latefee        = $input['latefee'] ?? 0;
            $receipt->interest       = $input['interest'] ?? 0;
            $receipt->totalamount    = $receipt->amount + $receipt->latefee + $receipt->interest;
            $receipt->entrydate = now();
            $receipt->entryby = auth()->user()->m_no ;
            $receipt->order_id = $input['order_id'];
            $receipt->cvmacs_rcpt_number = $nextRcptNumber;
            $receipt->save();

                if ((int)$input['towardscode'] == 46) {
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

                if ((int)$input['towardscode'] == 47) {
                    $mno = $receipt->m_no;

                    $loanissue = LoanIssue::where('accountno', $receipt ->loanacntno)->first();

                
                    if ($loanissue) {
                        // Update existing saving balance
                        $loanissue->loanpending -=  $receipt->amount;
                        $loanissue->lastpaiddate = $receipt->receipt_date;  
                        $loanissue->remaining_installments -= 1;
                        // if(!empty($input['loanclear']) && $input['loanclear'] == 1)
                        // {
                        //     $loanissue->status = 43;
                        //     $loanissue->loanpending = 0;
                        // }
                        if($loanissue->loanpending <= 0)
                        {
                            $loanissue->status = 43;
                            $loanissue->loanpending = 0;
                        }
                        $loanissue->save();

                    } 
                }

            return $receipt;
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment successful and receipt generated.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed.',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
