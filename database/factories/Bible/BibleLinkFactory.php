<?php

namespace Database\Factories\Bible;

use App\Models\Bible\BibleLink;
use Illuminate\Database\Eloquent\Factories\Factory;

class BibleLinkFactory extends Factory
{
    protected $model = BibleLink::class;

    public function definition(): array
    {
        return [
            'type'      => $this->faker->randomElement(['pdf', 'web', 'print', 'cat']),
            'url'       => $this->faker->url,
            'title'     => $this->faker->title(),
            'provider'  => $this->faker->title(),
            'visibile'  => $this->faker->boolean(80),
        ];
    }
}
