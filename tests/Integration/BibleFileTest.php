<?php

namespace Tests\Integration;

use App\Models\Bible\BibleFile;
use App\Models\Bible\Book;
use App\Models\User\Key;
use App\Traits\AccessControlAPI;

class BibleFileTest extends ApiV4NewTest
{
    use AccessControlAPI;

    /**
     * @category V4_API
     */
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('integration')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleFiles()
    {
        $bible_file = BibleFile::with('book')
            ->limit(1)
            ->first();

        $book = Book::where('id', $bible_file->book->id)->first();

        $this->assertEquals($book->id, $bible_file->book->id);
    }
}
