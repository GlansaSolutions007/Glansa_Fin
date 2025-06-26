<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MainBranch extends Model
{
    use HasFactory;

    protected $table = 'main_branch'; // since table name is not plural

    protected $primaryKey = 'main_branch_id';

    public $timestamps = false; // if you don't have created_at / updated_at

    protected $fillable = [
        'name',
        'subscription_start',
        'subscription_end',
        'subscription_status',
        'company_address',
        'mandal',
        'dist',
        'pincode',
        'entrydate'
    ];

    // Example relationship if a company has users
    public function users()
    {
        return $this->hasMany(User::class, 'main_branch_id', 'main_branch_id');
    }
}
