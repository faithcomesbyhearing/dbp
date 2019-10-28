<?php

namespace App\Transformers;

use App\Models\User\Study\Note;
use League\Fractal\TransformerAbstract;

class UserNotesTransformer extends TransformerAbstract
{
    /**
     * @OA\Schema (
     *        type="array",
     *        schema="v4_notes_index",
     *        description="The transformed user notes",
     *        title="v4_user_notes",
     *      @OA\Xml(name="v4_notes_index"),
     *      @OA\Items(
     *          @OA\Property(property="id",             ref="#/components/schemas/Note/properties/id"),
     *          @OA\Property(property="bible_id",       ref="#/components/schemas/Note/properties/bible_id"),
     *          @OA\Property(property="book_id",        ref="#/components/schemas/Note/properties/book_id"),
     *          @OA\Property(property="book_name",              ref="#/components/schemas/BibleBook/properties/name"),
     *          @OA\Property(property="chapter",        ref="#/components/schemas/Note/properties/chapter"),
     *          @OA\Property(property="verse_start",    ref="#/components/schemas/Note/properties/verse_start"),
     *          @OA\Property(property="verse_end",      ref="#/components/schemas/Note/properties/verse_end"),
     *          @OA\Property(property="verse_text",     ref="#/components/schemas/BibleFile/properties/verse_text"),
     *          @OA\Property(property="notes",          ref="#/components/schemas/Note/properties/notes"),
     *          @OA\Property(property="created_at",     ref="#/components/schemas/Note/properties/created_at"),
     *          @OA\Property(property="updated_at",     ref="#/components/schemas/Note/properties/updated_at"),
     *          @OA\Property(property="tags",           ref="#/components/schemas/AnnotationTag"),
     *        )
     *    )
     *)
     *
     * @param Note $note
     * @return array
     */
    public function transform(Note $note)
    {
        return [
      'id' => (int) $note->id,
      'bible_id' => (string) $note->bible_id,
      'book_id' => (string) $note->book_id,
      'book_name' => (string) optional($note->book)->name,
      'chapter' => (int) $note->chapter,
      'verse_start' => (int) $note->verse_start,
      'verse_end' => (int) $note->verse_end,
      'verse_text' => (string) $note->verse_text,
      'notes' => (string) $note->notes,
      'created_at' => (string) $note->created_at,
      'updated_at' => (string) $note->updated_at,
      'tags' => $note->tags
    ];
    }
}
