<?php

namespace Database\Factories\Bible;

use App\Models\Bible\BibleFile;
use Illuminate\Database\Eloquent\Factories\Factory;

class BibleFileFactory extends Factory
{
    protected $model = BibleFile::class;

    public function definition(): array
    {
        return [
            'id'            => '',
            'hash_id'       => '',
            'chapter_start' => random_int(1, 150),
            'chapter_end'   => random_int(1, 150),
            'verse_start'   => random_int(1, 179),
            'verse_end'     => random_int(1, 179),
        ];
    }
}
