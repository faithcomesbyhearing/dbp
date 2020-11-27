<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class SwaggerDocsController extends Controller
{
    public function swaggerDatabase()
    {
        $docs = json_decode(
            file_get_contents(public_path('/swagger_database.json')),
            true
        );
        return view('docs.swagger_database', compact('docs'));
    }
}
