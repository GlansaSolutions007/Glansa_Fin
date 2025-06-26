<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipts extends Model
{
    protected $table = 'receipts';
    protected $primaryKey = 'receipts_id';

    public $timestamps = false; 

    protected $fillable = [
        'company_id',
        'receipt_date',
        'cvmacs_rcpt_number',
        'srlno',
        'm_no',
        'membername',
        'towardscode',
        'towards',
        'trantypecode',
        'trantypename',
        'amount',
        'interest',
        'intonopsavings',
        'intonaddedsavings',
        'debtamount',
        'latefee',
        'totalamount',
        'totalsavings',
        'loanacntno',
        'loantypecode',
        'loantypename',
        'loanpending',
        'modeofrepayment',
        'Clearancedate',
        'Loan_Issue',
        'lastpaiddate',
        'noofmonths',
        'roi',
        'chqno',
        'chqdate',
        'chqamount',
        'bankname',
        'brname',
        'ifsccode',
        'entrydate',
        'entryby',
        'modifydate',
        'modifyby',
        'isallowflag',
    ];

    public function member()
    {
            return $this->belongsTo(Member::class, 'm_no' ,'m_no');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'entryby');
    }

}
