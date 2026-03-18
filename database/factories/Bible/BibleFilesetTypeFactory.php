<?php

namespace Database\Factories\Bible;

use App\Models\Bible\BibleFilesetType;
use Illuminate\Database\Eloquent\Factories\Factory;

class BibleFilesetTypeFactory extends Factory
{
    protected $model = BibleFilesetType::class;

    public function definition(): array
    {
        return [
            'set_type_code' => substr($this->faker->unique()->slug, 0, 15),
            'name'          => $this->faker->unique()->name,
        ];
    }
}
