<?php

namespace App\Transformers;

use Illuminate\Database\Eloquent\Collection;
use App\Models\User\Study\Highlight;
use App\Models\User\Study\Bookmark;
use App\Models\User\Study\Note;
use App\Models\User\Annotations;

class UsersDownloadAnnotationsTransFormer extends BaseTransformer
{
     /**
     *
     * @param Collection $annotation
     *
     * @return array
     */
    public function transform(Annotations $annotations) : ?Array
    {
        if ($this->route === 'v4_users_download_annotations.index') {
            return $this->getTransformRowByClass($annotations);
        }

        return [];
    }

    private function getTransformRowByClass(Annotations $annotations): ?Array
    {
        return [
            Annotations::NOTES_ANNOTATION => $this->getNotes($annotations->notes),
            Annotations::BOOKMARKS_ANNOTATION => $this->getBookmarks($annotations->bookmarks),
            Annotations::HIGHLIGHTS_ANNOTATION => $this->getHighlights($annotations->highlights),
        ];
    }


    private function getNotes(Collection $notes) : Array
    {
        $result = [];
        foreach ($notes as $note) {
            $result[] = $this->note($note);
        }
        return $result;
    }

    private function getBookmarks(Collection $bookmarks) : Array
    {
        $result = [];
        foreach ($bookmarks as $bookmark) {
            $result[] = $this->bookmark($bookmark);
        }
        return $result;
    }

    private function getHighlights(Collection $highlights) : Array
    {
        $result = [];
        foreach ($highlights as $highlight) {
            $result[] = $this->highlight($highlight);
        }
        return $result;
    }

    private function note(Note $note) : Array
    {
        return [
            'bible_id'        => (string) $note->bible_id,
            'book_id'         => (string) $note->book_id,
            'chapter'         => (int) $note->chapter,
            'verse_start'     => $note->verse_sequence,
            'verse_start_alt' => $note->verse_start,
            'verse_end'       => $note->verse_end ? (int) $note->verse_end : null,
            'verse_end_alt'   => $note->verse_end,
            'notes'           => (string) $note->notes
        ];
    }

    private function bookmark(Bookmark $bookmark) : Array
    {
        return [
            'bible_id'   => (string) $bookmark->bible_id,
            'book_id'    => (string) $bookmark->book_id,
            'chapter'    => (int) $bookmark->chapter,
            'verse'           => $bookmark->verse_sequence,
            'verse_start_alt' => $bookmark->verse_start
        ];
    }

    private function highlight(Highlight $highlight) : Array
    {
        $this->checkColorPreference($highlight);

        return [
            'bible_id'          => (string) $highlight->bible_id,
            'book_id'           => (string) $highlight->book_id,
            'chapter'           => (int) $highlight->chapter,
            'verse_start'       => $highlight->verse_sequence,
            'verse_start_alt'   => $highlight->verse_sequence,
            'verse_end'         => $highlight->verse_end ? (int) $highlight->verse_end : null,
            'verse_end_alt'     => $highlight->verse_end,
            'highlighted_color' => $highlight->color
        ];
    }

    private function checkColorPreference(Highlight $highlight) : void
    {
        $color_preference = checkParam('prefer_color') ?? 'rgba';
        $highlight->color = Highlight::checkAndReturnColorPreference($highlight, $color_preference);
    }
}
