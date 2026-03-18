<?php

namespace Database\Factories\User;

use App\Models\User\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'theme_id'         => 1,
            'location'         => $this->faker->streetAddress,
            'bio'              => $this->faker->paragraph(2, true),
            'twitter_username' => $this->faker->userName,
            'github_username'  => $this->faker->userName,
        ];
    }
}
