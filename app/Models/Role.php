<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;

class  Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    

     protected $fillable = [
        'name',
        'company_id'
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

}
