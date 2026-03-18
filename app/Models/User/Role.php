<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\User\RoleFactory::new();
    }

    protected $connection = 'dbp_users';
    protected $table = 'roles';
}
