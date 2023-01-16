<?php

namespace App\Transformers;

use App\Models\User\Study\Note;
use League\Fractal\TransformerAbstract;

class PlaylistNotesTransformer extends TransformerAbstract
{
    /**
     * @OA\Schema (
     *        type="object",
     *        schema="v4_internal_playlist_user_notes",
     *        description="The transformed user notes",
     *        title="v4_internal_playlist_user_notes",
     *      @OA\Xml(name="v4_internal_playlist_user_notes"),
     *   @OA\Property(property="data", type="array",
     *      @OA\Items(
     *          @OA\Property(property="id",             type="integer"),
     *          @OA\Property(property="user_id",        ref="#/components/schemas/User/properties/id"),
     *          @OA\Property(property="bible_id",       ref="#/components/schemas/Bible/properties/id"),
     *          @OA\Property(property="book_id",        ref="#/components/schemas/Book/properties/id"),
     *          @OA\Property(property="chapter",        ref="#/components/schemas/Note/properties/chapter"),
     *          @OA\Property(property="verse_start",    ref="#/components/schemas/Note/properties/verse_start"),
     *          @OA\Property(property="verse_start_alt",ref="#/components/schemas/Note/properties/verse_start"),
     *          @OA\Property(property="verse_end",      ref="#/components/schemas/Note/properties/verse_end"),
     *          @OA\Property(property="verse_end_alt",  ref="#/components/schemas/Note/properties/verse_end"),
     *          @OA\Property(property="tags",           ref="#/components/schemas/AnnotationTag")
     *      )
     *   )
     *)
     *
     * @param Note $note
     * @return array
     */
    public function transform(Note $note)
    {
        return [
            "id"            => (int) $note->id,
            "user_id"       => (int) $note->user_id,
            "notes"         => (string) $note->notes,
            "bible_id"      => (string) $note->bible_id,
            "book_id"       => (string) $note->book_id,
            "chapter"       => (int) $note->chapter,
            "verse_start"   => $note->verse_sequence,
            "verse_start_alt"=> $note->verse_start,
            "verse_end"     => (int) $note->verse_end,
            "verse_end_alt" => $note->verse_end,
            'tags'          => $note->tags,
        ];
    }
}
