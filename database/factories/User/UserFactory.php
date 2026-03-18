<?php

namespace Database\Factories\User;

use App\Models\User\Key;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        static $password;

        return [
            'name'                           => $this->faker->unique()->userName,
            'first_name'                     => $this->faker->firstName,
            'last_name'                      => $this->faker->lastName,
            'email'                          => $this->faker->unique()->safeEmail,
            'password'                       => $password ?: $password = bcrypt('secret'),
            'token'                          => Str::random(64),
            'activated'                      => true,
            'remember_token'                 => Str::random(10),
            'signup_ip_address'              => $this->faker->ipv4,
            'signup_confirmation_ip_address' => $this->faker->ipv4,
        ];
    }

    public function developer(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->keys()->save(Key::factory()->make());
        });
    }
}
