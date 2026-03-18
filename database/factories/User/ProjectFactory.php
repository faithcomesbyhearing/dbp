<?php

namespace Database\Factories\User;

use App\Models\User\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'id'               => random_int(0, 9999),
            'name'             => $this->faker->name,
            'url_avatar'       => $this->faker->url,
            'url_avatar_icon'  => $this->faker->url,
            'url_site'         => $this->faker->url,
            'description'      => $this->faker->paragraph(3, true),
            'sensitive'        => false,
            'deleted_at'       => null,
        ];
    }

    public function sensitive(): static
    {
        return $this->state(['sensitive' => true]);
    }
}
