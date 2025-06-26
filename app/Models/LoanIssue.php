<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\DropDownOptions;

class LoanIssue extends Model
{
    use HasFactory;

    protected $table = 'loanissues'; // Table name
    protected $primaryKey = 'loan_id'; // Primary key for the table
    public $timestamps = false; // Automatically manage timestamps

    // Columns that are mass assignable
    protected $fillable = [
        'loan_id', 'company_id', 'accountno', 'mno', 'mname', 'typecode', 'typename',
        'modeofrepaymentcode', 'modeofrepayment', 'purposecode', 'purpose', 'mshipmonths',
        'roi', 'totalsavingamt', 'status', 'appdate', 'issuedate', 'clearancedate',
        'lastpaiddate', 'eligibleamt', 'eligibleinstallments', 'issueamount', 'instamount',
        'installments', 'remaining_installments', 'debtamount', 'loanpending', 'surity1mno', 'surity1mname',
        'surity1details', 'surity2mno', 'surity2mname', 'surity2details', 'surity3mno',
        'surity3mname', 'surity3details', 'surity4mno', 'surity4mname', 'surity4details',
        'surity_chqno', 'surity_chqamount', 'surity_ifsccode', 'surity_bankbrname',
        'entrydate', 'entryby', 'modifydate', 'modifyby','statusname'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'mno', 'm_no'); // 'mno' is the foreign key in LoanIssue, 'm_no' is the primary key in Member
    }
    public function statusOption()
    {
         return $this->belongsTo(DropDownOptions::class, 'status', 'id'); 
        // return $this->belongsTo(DropDownOptions::class, 'status')
        //             ->where('type', 'loan_status');
    }
}
