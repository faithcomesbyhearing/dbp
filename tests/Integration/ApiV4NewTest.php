<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User\Key;

class ApiV4NewTest extends TestCase
{
    protected $params;
    protected $key;

    protected function setUp() : void
    {
        parent::setUp();

        $this->key    = Key::where('name', 'bible.is mobile')->first()->key;
        $this->params = [
            'v' => 4,
            'key' => $this->key,
            'pretty'
        ];
    }
}
