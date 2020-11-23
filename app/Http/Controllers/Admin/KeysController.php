<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\APIController;

class KeysController extends APIController
{
    public function requested()
    {
        return view('v4.admin.requested_key');
    }
}
