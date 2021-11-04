<?php

namespace App\Http\Controllers\Bible\Traits;

use App\Models\Bible\BibleVerse;

trait TextControllerTrait
{
    public function getVerses($cache_params, $fileset, $bible, $book, $chapter, $verse_start, $verse_end)
    {
        return cacheRemember(
            'bible_text',
            $cache_params,
            now()->addDay(),
            function () use ($fileset, $bible, $book, $chapter, $verse_start, $verse_end) {
                $select_columns = [
                    'bible_verses.book_id as book_id',
                    'books.name as book_name',
                    'books.protestant_order as book_order',
                    'bible_books.name as book_vernacular_name',
                    'bible_verses.chapter',
                    'bible_verses.verse_start',
                    'bible_verses.verse_end',
                    'bible_verses.verse_text',
                ];

                if ($bible && $bible->numeral_system_id) {
                    $select_columns = array_merge(
                        $select_columns,
                        [
                            'glyph_chapter.glyph as chapter_vernacular',
                            'glyph_start.glyph as verse_start_vernacular',
                            'glyph_end.glyph as verse_end_vernacular',
                        ]
                    );
                }
                return BibleVerse::withVernacularMetaData($bible)
                    ->where('hash_id', $fileset->hash_id)
                    ->when($book, function ($query) use ($book) {
                        return $query->where('bible_verses.book_id', $book->id);
                    })
                    ->when($verse_start, function ($query) use ($verse_start) {
                        return $query->where('verse_end', '>=', $verse_start);
                    })
                    ->when($chapter, function ($query) use ($chapter) {
                        return $query->where('chapter', $chapter);
                    })
                    ->when($verse_end, function ($query) use ($verse_end) {
                        return $query->where('verse_end', '<=', $verse_end);
                    })
                    ->orderBy('verse_start')
                    ->select($select_columns)->get();
            }
        );
    }
}
