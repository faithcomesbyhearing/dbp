<?php

namespace App\Transformers\V2\Annotations;

use League\Fractal\TransformerAbstract;

class HighlightTransformer extends TransformerAbstract
{
    /**
     * This transformer modifies the Highlight response to reflect
     * the expected return for the old Version 2 DBP api routes
     * and regenerates the aged dam_id from the new bible_id
     *
     * @see Controller: \App\Http\Controllers\Connections\V2Controllers\UsersControllerV2::annotationHighlight
     * @see Old Route:  http://api.bible.is/annotations/highlight?dbt_data=1&dbt_version=2&hash=test_hash&key=test_key&reply=json&user_id=313117&v=1
     * @see New Route:  https://api.dbp.test/v2/annotations/highlight?key=test_key&pretty&v=2&user_id=5
     *
     * @param $highlight
     * @return array
     */
    public function transform($highlight)
    {
        $dam_id = $highlight->bible_id.substr($highlight->bibleBook->book->book_testament, 0, 1).'2ET';
        $highlight_fileset_info = $highlight->fileset_info;
        $verse_text = $highlight_fileset_info->get('verse_text');
        $audio_filesets = $highlight_fileset_info->get('audio_filesets');

        return [
            'id'                   => (string) $highlight->id,
            'user_id'              => (string) $highlight->user_id,
            'dam_id'               => $dam_id,
            'book_id'              => (string) $highlight->bibleBook->book->id_osis,
            'chapter_id'           => (string) $highlight->chapter,
            'verse_id'             => $highlight->verse_sequence,
            'verse_start_alt'      => $highlight->verse_start,
            'color'                => $highlight->color->color ?? 'green',
            'created'              => (string) $highlight->created_at,
            'updated'              => (string) $highlight->updated_at,
            'dbt_data'             => [[
                'book_name'        => (string) $highlight->bibleBook->name,
                'book_id'          => (string) $highlight->book_id,
                'book_order'       => (string) $highlight->bibleBook->book->protestant_order,
                'chapter_id'       => (string) $highlight->chapter,
                'chapter_title'    => 'Chapter '.$highlight->chapter,
                'verse_id'         => $highlight->verse_sequence,
                'verse_start_alt'  => $highlight->verse_start,
                'verse_text'       => $verse_text,
                'paragraph_number' => '1',
                'audio_filesets'   => $audio_filesets
            ]]
        ];
    }
}
