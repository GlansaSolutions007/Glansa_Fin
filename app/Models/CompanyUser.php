<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class CompanyUser extends Authenticatable
{
    protected $table = 'company';
    protected $primaryKey = 'company_id';
    public $timestamps = false;
    protected $fillable = [
        'name', 
        'status',
        'min_saving',
        'admission_fee',
        'form_fee',
        'loan_eligibility',
        'eligibility_amount',
        'date',
        'main_branch_id',
    ];

    public function company()
    {
        return $this->belongsTo(MainBranch::class, 'main_branch_id', 'main_branch_id');
    }

}
