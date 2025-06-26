<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanLateFee extends Model
{
    protected $table = 'loan_late_fee';

    protected $primaryKey = 'late_id';

    public $timestamps = false;

    protected $fillable = [
        'late_fee', 'from_date', 'to_date', 'updated_on'
    ];
}
