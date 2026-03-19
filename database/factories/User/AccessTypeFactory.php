<?php

namespace Database\Factories\User;

use App\Models\User\AccessType;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccessTypeFactory extends Factory
{
    protected $model = AccessType::class;

    public function definition(): array
    {
        return [
            'name'         => 'Test Generated ' . $this->faker->companySuffix,
            'country_id'   => null,
            'continent_id' => null,
            'allowed'      => 1,
        ];
    }

    public function withCountry(): static
    {
        return $this->state(['country_id' => $this->faker->countryCode]);
    }

    public function withContinent(): static
    {
        return $this->state(['continent_id' => $this->faker->randomElement(['EU', 'AS', 'NA', 'AF', 'SA', 'OC', 'AN'])]);
    }
}
