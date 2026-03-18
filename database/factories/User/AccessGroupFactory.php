<?php

namespace Database\Factories\User;

use App\Models\User\AccessGroup;
use App\Models\User\AccessType;
use App\Models\Bible\BibleFileset;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccessGroupFactory extends Factory
{
    protected $model = AccessGroup::class;

    public function definition(): array
    {
        return [
            'name'        => 'Test Generated ' . $this->faker->companySuffix,
            'description' => $this->faker->paragraph(random_int(1, 5)),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (AccessGroup $group) {
            $group->types()->attach(AccessType::factory()->count(random_int(1, 5))->create());
            $group->filesets()->attach(BibleFileset::factory()->count(random_int(1, 5))->create());
        });
    }
}
