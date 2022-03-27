<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\APIController;
use Illuminate\Support\Facades\Redirect;

class DocsController extends APIController
{
    /**
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        return view('docs.routes.index');
    }

    public function start()
    {
        return Redirect::to(config('app.get_started_url'));
    }

    public function bibles()
    {
        return view('docs.routes.bibles');
    }

    public function books()
    {
        return view('docs.routes.books');
    }

    public function languages()
    {
        return view('docs.routes.languages');
    }

    public function countries()
    {
        return view('docs.routes.countries');
    }

    public function alphabets()
    {
        return view('docs.routes.alphabets');
    }

    public function bookOrderListing()
    {
        return view('docs.v2.books.bookOrderListing');
    }
}
