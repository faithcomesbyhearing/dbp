<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class SwaggerDocsController extends Controller
{
    public function swaggerDocsGen($version)
    {
        if (file_exists(public_path('openapi.json'))) {
            $swagger = file_get_contents(public_path('openapi.json'));
            return response($swagger)->header('Content-Type', 'application/json');
        } else {
            return response('Not Found', 404);
        }
    }
}
