<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $connection = 'dbp_users';
    protected $table = 'roles';
}
