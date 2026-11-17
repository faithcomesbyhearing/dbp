<?php

namespace Database\Factories\Bible;

use App\Models\Bible\BibleVerse;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

class BibleVerseFactory extends Factory
{
    protected $model = BibleVerse::class;

    public function definition(): array
    {
        return [
            'hash_id'     => BibleFileset::where('set_type_code', 'text_plain')->inRandomOrder()->first()->hash_id,
            'book_id'     => Book::inRandomOrder()->first()->id,
            'chapter'     => random_int(1, 150),
            'verse_start' => random_int(1, 176),
            'verse_text'  => $this->faker->paragraph,
        ];
    }
}
