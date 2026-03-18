<?php

namespace Database\Factories\Language;

use App\Models\Language\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        return [
            'glotto_id'  => $this->faker->unique()->bothify('????????'),
            'iso'        => $this->faker->lexify('???'),
            // iso2B / iso2T / iso1 carry UNIQUE indexes with very small key spaces
            // (≈26^3 / 26^2) that already collide against existing rows; they are
            // nullable, so leave null to keep the factory persistable.
            'iso2B'      => null,
            'iso2T'      => null,
            'iso1'       => null,
            'name'       => $this->faker->colorName . ' ' . $this->faker->monthName,
            // country_id / status_id are nullable FKs; leave null so the factory does not
            // depend on seeded countries / language_status rows (random ids would violate the FK).
            'country_id' => null,
            'status_id'  => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Language $language) {
            // Self-reference both required FKs to this language so creating a Language does not
            // recursively spawn additional Language rows through the translation factory defaults.
            $language->translations()->save(
                LanguageTranslationFactory::new()->make([
                    'language_source_id'      => $language->id,
                    'language_translation_id' => $language->id,
                ])
            );
        });
    }
}
