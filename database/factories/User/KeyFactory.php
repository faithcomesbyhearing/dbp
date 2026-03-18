<?php

namespace Database\Factories\User;

use App\Models\User\Key;
use Illuminate\Database\Eloquent\Factories\Factory;

class KeyFactory extends Factory
{
    protected $model = Key::class;

    public function definition(): array
    {
        return [
            'key'         => $this->faker->bankAccountNumber,
            'name'        => $this->faker->title,
            'description' => $this->faker->paragraph,
        ];
    }
}
