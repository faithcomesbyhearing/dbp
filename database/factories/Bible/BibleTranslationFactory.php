<?php

namespace Database\Factories\Bible;

use App\Models\Bible\BibleTranslation;
use App\Models\Language\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

class BibleTranslationFactory extends Factory
{
    protected $model = BibleTranslation::class;

    public function definition(): array
    {
        return [
            'language_id' => Language::factory(),
            'vernacular'  => false,
            'name'        => $this->faker->title,
            'description' => $this->faker->paragraph(random_int(1, 3)),
        ];
    }

    public function vernacular(): static
    {
        return $this->state(['vernacular' => true]);
    }
}
