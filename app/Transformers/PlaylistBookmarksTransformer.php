<?php

namespace App\Transformers;

use App\Models\User\Study\Bookmark;
use League\Fractal\TransformerAbstract;

class PlaylistBookmarksTransformer extends TransformerAbstract
{
    /**
     * @OA\Schema (
     *        type="object",
     *        schema="v4_internal_playlist_user_bookmarks",
     *        description="The transformed user bookmarks",
     *        title="v4_internal_playlist_user_bookmarks",
     *      @OA\Xml(name="v4_internal_playlist_user_bookmarks"),
     *   @OA\Property(property="data", type="array",
     *      @OA\Items(
     *          @OA\Property(property="id",       type="integer"),
     *          @OA\Property(property="bible_id", ref="#/components/schemas/Bible/properties/id"),
     *          @OA\Property(property="book_id",  ref="#/components/schemas/Book/properties/id"),
     *          @OA\Property(property="chapter",  ref="#/components/schemas/Bookmark/properties/chapter"),
     *          @OA\Property(property="verse",    ref="#/components/schemas/Bookmark/properties/verse_start"),
     *          @OA\Property(property="tags",     ref="#/components/schemas/AnnotationTag")
     *      )
     *   )
     *)
     *
     * @param Bookmark $bookmark
     * @return array
     */
    public function transform(Bookmark $bookmark)
    {
        return [
            'id'             => (int) $bookmark->id,
            'bible_id'       => (string) $bookmark->bible_id,
            'book_id'        => (string) $bookmark->book_id,
            'chapter'        => (int) $bookmark->chapter,
            'verse'          => $bookmark->verse_sequence,
            'verse_start_alt'=> $bookmark->verse_start,
            'tags'           => $bookmark->tags
        ];
    }
}
