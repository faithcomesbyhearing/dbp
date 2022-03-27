<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;

class BibleFilesetCopyrightRole extends Model
{
    const HOLDER = 1;
    const LICENSOR = 2;
    const PARTNER = 3;

    protected $connection = 'dbp';
    public $table = 'bible_fileset_copyright_roles';
}
