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
}
