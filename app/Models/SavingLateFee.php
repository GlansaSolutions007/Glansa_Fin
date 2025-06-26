<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingLateFee extends Model
{
    
    protected $table = 'saving_late_fee';

    protected $primaryKey = 'late_id';

    public $timestamps = false;

    protected $fillable = [
        'saving_fee', 'from_date', 'to_date', 'updated_on'
    ];
}
