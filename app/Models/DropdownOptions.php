<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DropdownOptions extends Model
{

    // Define the table name
    protected $table = 'dropdown_options';

    // Define the primary key (optional if not 'id')
    protected $primaryKey = 'id';

    // Define the fields that can be mass-assigned
    protected $fillable = [
        'name',
        'parent_id',
        'status_id'
    ];

    // Disable timestamps if your table doesn't have created_at/updated_at
    public $timestamps = false;
}
