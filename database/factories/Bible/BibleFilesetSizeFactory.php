<?php

namespace Database\Factories\Bible;

use App\Models\Bible\BibleFilesetSize;
use Illuminate\Database\Eloquent\Factories\Factory;

class BibleFilesetSizeFactory extends Factory
{
    protected $model = BibleFilesetSize::class;

    public function definition(): array
    {
        return [
            'set_size_code' => substr($this->faker->unique()->slug, 0, 8),
            'name'          => $this->faker->unique()->name,
        ];
    }
}
