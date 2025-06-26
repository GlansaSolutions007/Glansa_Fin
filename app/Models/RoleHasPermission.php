<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleHasPermission extends Model
{
    protected $table = 'role_has_permissions';

    public $timestamps = false;

    protected $fillable = ['permission_id', 'role_id' ,'company_id'];
}
