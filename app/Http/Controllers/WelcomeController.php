<?php

namespace App\Http\Controllers;

class WelcomeController extends APIController
{
    public function redirect()
    {
        return view('redirect');
    }

}
