<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class SwaggerDocsController extends Controller
{
    public function swaggerDatabase()
    {
        $docs = json_decode(file_get_contents(public_path('/swagger_database.json')), true);
        return view('docs.swagger_database', compact('docs'));
    }

    public function swaggerDatabaseModel($id)
    {
        $docs = json_decode(file_get_contents(public_path('/swagger_database.json')), true);
        if (!isset($docs['components']['schemas'][$id]['properties'])) {
            return $this->setStatusCode(404)->replyWithError('Missing Model');
        }

        return view('docs.swagger_database', compact('docs', 'id'));
    }

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
