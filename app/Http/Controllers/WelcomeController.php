<?php

namespace App\Http\Controllers;

class WelcomeController extends APIController
{
    public function welcome()
    {
        return view('welcome');
    }

    // Legal

    public function legal()
    {
        return view('about.legal.overview');
    }

    public function license()
    {
        return view('about.legal.license');
    }

    public function privacyPolicy()
    {
        //return view('about.legal.privacy_policy');
        Redirect::to("https://www.faithcomesbyhearing.com/privacy-policy");
    }

    public function terms()
    {
        return view('about.legal.terms');
    }
}
