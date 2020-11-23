<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\APIController;
use Auth;

class DashboardController extends APIController
{

    /**
     * Create a new controller instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware('auth');
    }

    public function home()
    {
        $user = Auth::user() ?? $this->user;
        return view('v4.admin.dashboard', compact('user'));
    }
}
