<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Member extends Authenticatable implements JWTSubject
{
    protected $table = 'members'; // Optional, Laravel uses 'members' by default for 'Member'
    protected $primaryKey = 'm_no';
    protected $fillable = [
        'member_id',
        'company_id',
        'name',
        'aliasname',
        'surname',
        'image',
        'designation',
        'doj',
        'dob',
        'occupan',
        'aadhaarno',
        'panno',
        'stayingwith',
        'swname',
        'swoccupan',
        'nomineename',
        'rwnominee',
        'tfamilymembers',
        'femalecnt',
        'malecnt',
        'mobile1',
        'mobile2',
        'landline',
        'refmno',
        'isownresidence',
        'tmphno',
        'tmpcolony',
        'tmpmandal',
        'tmpdist',
        'tmplandmark',
        'tmppin',
        'prmnthno',
        'prmntcolony',
        'prmntmandal',
        'prmntdist',
        'prmntlandmark',
        'prmntpin',
        'depositamount',
        'sharecapital',
        'acntno',
        'acntname',
        'ifsccode',
        'bankname',
        'issuspended',
        'wstatusdate',
        'wstatus',
        'wreasoncode',
        'wreason',
        'withdrawappby',
        'wapplicantname',
        'relnwith_wapplicant',
        'suritymno',
        'entryby',
        'entrydate',
        'modifyby',
        'modifydate',
        'reason',
        'isactive',
    ];

    public $timestamps = false; // Add this if your table does NOT have `created_at`, `updated_at` columns

    // Define the relationship between Member and LoanIssue
    public function loanissues()
    {
        return $this->hasMany(LoanIssue::class, 'mno', 'm_no'); // Assuming 'mno' is the foreign key in loanissues and 'm_no' is the local key in members
    }

    // Define the relationship for savings (assuming one-to-one or one-to-many)
    public function savings()
    {
        return $this->hasMany(Savings::class, 'm_no', 'm_no'); // Adjust according to your schema
    }

      // Implement the required methods
      public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
    
}
