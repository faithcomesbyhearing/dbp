<?php

namespace Database\Factories\User;

use App\Models\User\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->name,
            'slug'        => Str::slug($this->faker->name),
            'description' => $this->faker->name,
        ];
    }
}
