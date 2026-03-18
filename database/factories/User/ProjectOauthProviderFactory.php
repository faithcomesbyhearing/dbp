<?php

namespace Database\Factories\User;

use App\Models\User\Project;
use App\Models\User\ProjectOauthProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectOauthProviderFactory extends Factory
{
    protected $model = ProjectOauthProvider::class;

    public function definition(): array
    {
        return [
            'name'          => collect(['facebook', 'google', 'twitter', 'github'])->random(),
            'client_id'     => '',
            'client_secret' => '',
            'callback_url'  => $this->faker->url,
            'redirect_url'  => $this->faker->url,
            'description'   => $this->faker->paragraph(2, true),
            'project_id'    => Project::factory(),
        ];
    }
}
