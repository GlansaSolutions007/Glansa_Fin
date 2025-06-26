<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntProcessOnSavings extends Model
{
    protected $table = 'intprocessonsavings';

    protected $fillable = [
        'company_id',
        'intonsavings_code',
        'intonsavings_rcptsmonth',
        'intonsavings_processingmonth',
        'intonsavings_isprocessed',
        'intonsavings_monthlyroi',
        'intonsavings_currfinyr',
        'intonsavings_status',
    ];

    // Add timestamps if table has created_at, updated_at
    public $timestamps = true;
}
