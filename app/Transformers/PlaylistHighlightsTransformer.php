<?php

namespace App\Transformers;

use App\Models\User\Study\Highlight;

class PlaylistHighlightsTransformer extends UserHighlightsTransformer
{
    /**
     * @OA\Schema (
     *        type="object",
     *        schema="v4_internal_playlist_user_highlights",
     *        description="The transformed user highlights",
     *        title="v4_internal_playlist_user_highlights",
     *      @OA\Xml(name="v4_internal_playlist_user_highlights"),
     *   @OA\Property(property="data", type="array",
     *      @OA\Items(
     *          @OA\Property(property="id",       type="integer"),
     *          @OA\Property(property="user_id",  ref="#/components/schemas/User/properties/id"),
     *          @OA\Property(property="bible_id", ref="#/components/schemas/Bible/properties/id"),
     *          @OA\Property(property="book_id",  ref="#/components/schemas/Book/properties/id"),
     *          @OA\Property(property="chapter",  ref="#/components/schemas/Highlight/properties/chapter"),
     *          @OA\Property(
     *              property="verse_start",
     *              ref="#/components/schemas/Highlight/properties/verse_start"
     *          ),
     *          @OA\Property(
     *              property="verse_end",
     *              ref="#/components/schemas/Highlight/properties/verse_end"
     *          ),
     *          @OA\Property(
     *              property="highlight_start",
     *              ref="#/components/schemas/Highlight/properties/highlight_start"
     *          ),
     *          @OA\Property(
     *              property="highlighted_words",
     *              ref="#/components/schemas/Highlight/properties/highlighted_words"
     *          ),
     *          @OA\Property(
     *              property="highlighted_color",
     *              ref="#/components/schemas/Highlight/properties/highlighted_color"
     *          ),
     *          @OA\Property(property="tags", ref="#/components/schemas/AnnotationTag")
     *      )
     *   )
     *)
     *
     * @param Highlight $note
     * @return array
     */
    public function transform(Highlight $highlight)
    {
        $this->checkColorPreference($highlight);

        return [
            'id'                => (int) $highlight->id,
            'bible_id'          => (string) $highlight->bible_id,
            'book_id'           => (string) $highlight->book_id,
            'chapter'           => (int) $highlight->chapter,
            'verse_start'       => (int) $highlight->verse_start,
            'verse_end'         => (int) $highlight->verse_end,
            'highlight_start'   => (int) $highlight->highlight_start,
            'highlighted_words' => $highlight->highlighted_words,
            'highlighted_color' => $highlight->color,
            'tags'              => $highlight->tags,
        ];
    }
}
