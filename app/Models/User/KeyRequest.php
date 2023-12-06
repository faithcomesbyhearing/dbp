<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class KeyRequest extends Model
{
    protected $connection = 'dbp_users';
    protected $table = 'user_key_requests';
    protected $fillable = [
        'name',
        'email',
        'description',
        'questions',
        'temporary_key',
        'notes',
        'state',
        'key_id',
        'application_name',
        'application_url',
    ];

    protected $name;
    protected $application_name;
    protected $application_url;
    protected $email;
    protected $description;
    protected $questions;
    protected $temporary_key;
    protected $notes;
    protected $state;
    protected $key_id;

    public function generateKey()
    {
        $uuid = Uuid::uuid4();
        $this['temporary_key'] = $uuid->toString();
    }

    public function key()
    {
        return $this->belongsTo(Key::class);
    }
}
