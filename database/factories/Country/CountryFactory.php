<?php

namespace Database\Factories\Country;

use App\Models\Country\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'id'         => $this->faker->unique()->countryCode,
            'iso_a3'     => $this->faker->unique()->lexify('???'),
            'name'       => $this->faker->name,
            'fips'       => $this->faker->unique()->lexify('??'),
            'wfb'        => $this->faker->boolean,
            'ethnologue' => $this->faker->boolean,
            'continent'  => $this->faker->randomElement(['EU', 'AS', 'NA', 'AF', 'SA', 'OC', 'AN']),
        ];
    }
}
