<?php

namespace Database\Factories\Bible;

use App\Models\Bible\Bible;
use App\Models\Language\Language;
use App\Models\Language\NumeralSystem;
use App\Models\Language\Alphabet;
use Illuminate\Database\Eloquent\Factories\Factory;

class BibleFactory extends Factory
{
    protected $model = Bible::class;

    public function definition(): array
    {
        return [
            'id'                => strtoupper($this->faker->languageCode . $this->faker->lexify('???')),
            'language_id'       => Language::factory(),
            'versification'     => $this->faker->randomElement(['protestant', 'luther', 'synodal', 'german', 'kjva', 'vulgate', 'lxx', 'orthodox', 'nrsva', 'catholic', 'finnish']),
            'numeral_system_id' => NumeralSystem::factory(),
            'date'              => $this->faker->year(),
            'scope'             => '',
            'script'            => Alphabet::factory(),
            'copyright'         => '© ' . $this->faker->company,
            'in_progress'       => $this->faker->boolean(),
            'priority'          => random_int(0, 9),
            'reviewed'          => $this->faker->boolean(75),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Bible $bible) {
            $bible->translations()->save(BibleTranslationFactory::new()->vernacular()->make());
            $bible->translations()->saveMany(BibleTranslationFactory::new()->count(random_int(1, 3))->make());
            $bible->links()->saveMany(BibleLinkFactory::new()->count(random_int(1, 5))->make());
        });
    }
}
