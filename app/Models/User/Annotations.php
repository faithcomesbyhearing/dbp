<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Collection;

class Annotations
{
    const NOTES_ANNOTATION = 'notes';
    const BOOKMARKS_ANNOTATION = 'bookmarks';
    const HIGHLIGHTS_ANNOTATION = 'highlights';

    public $notes;
    public $bookmarks;
    public $highlights;

    public function __construct(Collection $new_notes, Collection $new_bookmarks, Collection $new_highlights)
    {
        $this->notes = $new_notes;
        $this->bookmarks = $new_bookmarks;
        $this->highlights = $new_highlights;
    }

    /**
     * Filters a collection of annotations based on a search query.
     *
     * This method searches for the query string within the 'verse_text' and 'book_name' fields of each annotation in
     * the provided collection. If the search query is found, the annotation is included in the returned collection.
     *
     * @param Collection|null $annotation_query The collection of annotations to be filtered. If null, the method will
     * return false.
     *
     * @param string $search_query The string to search for within the 'verse_text' and 'book_name' of each annotation.
     *
     * @return bool|Collection Returns a filtered collection of annotations where the search query was found. If the
     * input collection is null, the method will return false.
     *
     */
    public static function filterAnnotations(?Collection $annotation_query, string $search_query) : bool|Collection
    {
        return $annotation_query->filter(function ($annotation) use ($search_query) {
            $verse_text = $annotation->verse_text;
            $book = $annotation->bibleBook;
            $book_name = $book ? $book->name : null;

            if (isset($verse_text, $book, $book_name)) {
                return str_contains(strtolower($book_name . ' ' . $verse_text), $search_query);
            }
            return false;
        });
    }
}
