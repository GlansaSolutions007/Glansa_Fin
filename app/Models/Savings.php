<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Savings extends Model
{
    //
     protected $table = 'savings';

    // The primary key of the table (optional if 'id' is the default primary key)
    protected $primaryKey = 'savings_id';

    // Indicates if the model should be timestamped (if you have `created_at` and `updated_at` fields)
    public $timestamps = false;

    // Define the fillable fields
    protected $fillable = [
        'company_id', 
        'm_no',
        'mname', 
        'openingbal', 
        'added', 
        'intonopening',
        'intonadded', 
        'bonusamount', 
        'withdrawal', 
        'closingbal', 
        'roi', 
        'lastpaiddate',
        'oploanpending', 
        'remarks'
    ];

      public function member()
    {
        return $this->belongsTo(Member::class, 'm_no', 'm_no'); // Assuming 'm_no' is the foreign key in savings
    }
}
