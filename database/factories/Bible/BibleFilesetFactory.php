<?php

namespace Database\Factories\Bible;

use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleFilesetType;
use App\Models\Bible\BibleFilesetSize;
use App\Models\Organization\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

class BibleFilesetFactory extends Factory
{
    protected $model = BibleFileset::class;

    public function definition(): array
    {
        return [
            'id'            => strtoupper($this->faker->languageCode . $this->faker->lexify('????')),
            'hash_id'       => substr($this->faker->bankAccountNumber, 0, 11),
            'asset_id'      => Asset::factory(),
            'set_type_code' => fn () => BibleFilesetType::factory()->create()->set_type_code,
            'set_size_code' => fn () => BibleFilesetSize::factory()->create()->set_size_code,
            'hidden'        => $this->faker->boolean(10),
        ];
    }
}
