<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyPayment extends Model
{
    protected $table = 'company_payments';
    protected $primaryKey = 'id';
     protected $fillable = [
        'main_branch_id',
        'payments_id',
        'subscription_id',
        'subscription_start',
        'subscription_end',
        'amount',
        'created',
    ];

    public function main_branch()
    {
        return $this->belongsTo(MainBranch::class, 'main_branch_id');
    }
}
