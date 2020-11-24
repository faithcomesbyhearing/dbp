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

    public function coreConcepts()
    {
        return view('docs.routes.coreConcepts');
    }
    public function availableContent()
    {
        return view('docs.routes.availableContent');
    }
    public function apiReference()
    {
        return view('docs.routes.apiReference');
    }
    public function userFlows()
    {
        return view('docs.routes.userFlows');
    }

    public function start()
    {
        return Redirect::to(config('app.get_started_url'));
    }

    public function swagger($version)
    {
        return view('docs.swagger_docs');
    }

    public function bibles()
    {
        return view('docs.routes.bibles');
    }

    public function bibleEquivalents()
    {
        return view('docs.routes.bibleEquivalents');
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
