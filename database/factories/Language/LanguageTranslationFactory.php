<?php

namespace Database\Factories\Language;

use App\Models\Language\LanguageTranslation;
use App\Models\Language\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

class LanguageTranslationFactory extends Factory
{
    protected $model = LanguageTranslation::class;

    public function definition(): array
    {
        return [
            'language_source_id'      => fn () => Language::factory()->create()->id,
            'language_translation_id' => fn () => Language::factory()->create()->id,
            'name'                    => $this->faker->name,
            // priority is a NOT NULL unsigned int; keep it non-null (occasionally elevated).
            'priority'                => (random_int(0, 10) === 9) ? 9 : 0,
        ];
    }
}
