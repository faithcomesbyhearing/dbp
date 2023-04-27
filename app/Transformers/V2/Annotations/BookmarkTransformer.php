<?php

namespace App\Transformers\V2\Annotations;

use League\Fractal\TransformerAbstract;

class BookmarkTransformer extends TransformerAbstract
{
    /**
     * This transformer modifies the Bookmark response to reflect
     * the expected return for the old Version 2 DBP api route
     * and regenerates the old dam_id from the new bible_id
     *
     * @see Controller: \App\Http\Controllers\Connections\V2Controllers\UsersControllerV2::annotationBookmark
     * @see Old Route:  http://api.bible.is/annotations/bookmark?dbt_data=1&dbt_version=2&hash=test_hash&key=test_key&reply=json&user_id=313117&v=1
     * @see New Route:  https://api.dbp.test/v2/annotations/bookmark?key=test_key&pretty&v=2&user_id=5
     *
     * @param $bookmark
     * @return array
     */
    public function transform($bookmark)
    {
        return [
            'id'                   => (string) $bookmark->id,
            'user_id'              => (string) $bookmark->user_id,
            'dam_id'               => $bookmark->bible_id.substr($bookmark->bibleBook->book_testament, 0, 1).'2ET',
            'book_id'              => (string) $bookmark->bibleBook->id_osis ? $bookmark->bibleBook->id_osis : $bookmark->book_id,
            'chapter_id'           => (string) $bookmark->chapter,
            'verse_id'             => $bookmark->verse_sequence,
            'verse_start_alt'      => (string) $bookmark->verse_start,
            'created'              => (string) $bookmark->created_at,
            'updated'              => (string) $bookmark->updated_at,
            'dbt_data'             => [[
                'book_name'        => (string) $bookmark->bibleBook->name,
                'book_id'          => (string) $bookmark->bibleBook->id_osis,
                'book_order'       => (string) $bookmark->bibleBook->protestant_order,
                'chapter_id'       => (string) $bookmark->chapter,
                'chapter_title'    => trans('api.chapter_title_prefix').' '.$bookmark->chapter,
                'verse_id'         => $bookmark->verse_sequence,
                'verse_start_alt'  => $bookmark->verse_start,
                'verse_text'       => (string) $bookmark->verse_text,
                'paragraph_number' => '1'
            ]]
        ];
    }
}
