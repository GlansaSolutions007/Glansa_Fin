<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasRoles;
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
     protected $primaryKey = 'employeeid';
      public $timestamps = false; 
    protected $fillable = [
        'main_branch_id',
        'company_id', 
        'name', 
        'phonenumber', 
        'email', 
        'username', 
        'password',
        'usertype',
        'role_id',
        'status', 
        'entryby', 
        'entrydate',
        'doj',
        'dob',
        'surname',
        'image',
        'aadhaarno',
        'panno',
        'hno',
        'landmark',
        'colony',
        'dist',
        'mandal',
        'pincode',
        'acntno',
        'bankname',
        'ifsccode',
        'acntname',
        'bankname'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

     public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function mainbranch()
    {
        return $this->belongsTo(MainBranch::class, 'main_branch_id', 'main_branch_id');
    }
}
