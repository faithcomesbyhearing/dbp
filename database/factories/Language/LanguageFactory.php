<?php

namespace Database\Factories\Language;

use App\Models\Language\Language;
use App\Models\Country\Country;
use App\Models\Language\LanguageStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        return [
            'glotto_id'  => $this->faker->unique()->bothify('????????'),
            'iso'        => $this->faker->unique()->lexify('???'),
            'iso2B'      => $this->faker->lexify('???'),
            'iso2T'      => $this->faker->lexify('???'),
            'iso1'       => $this->faker->lexify('??'),
            'name'       => $this->faker->colorName . ' ' . $this->faker->monthName,
            'country_id' => fn () => Country::factory()->make()->id,
            'status_id'  => fn () => LanguageStatus::factory()->make()->id,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Language $language) {
            $language->translations()->save(LanguageTranslationFactory::new()->make());
        });
    }
}
