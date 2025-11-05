<?php

namespace App\Http\Controllers;

use League\Fractal\Serializer\DataArraySerializer;

class APINoKeyController extends APIController
{
    /**
     * APINoKeyController constructor.
     */

    public function __construct()
    {
        // ensure we set the api flag to true and version to v4 to resolve the API routes
        $this->api = true;
        $this->v = "v4";
        $this->serializer = new DataArraySerializer();
    }
}
