<?php

namespace Database\Factories\Language;

use App\Models\Language\LanguageStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class LanguageStatusFactory extends Factory
{
    protected $model = LanguageStatus::class;

    public function definition(): array
    {
        return [
            'id'          => $this->faker->unique()->bothify('??'),
            'title'       => $this->faker->name,
            'description' => $this->faker->paragraph,
        ];
    }
}
