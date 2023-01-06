<?php

namespace App\Transformers;

use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleVerse;

class BibleVerseTransformer extends BaseTransformer
{
    /**
     * A Fractal transformer.
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_bible_verses.all",
     *   description="The bibles being returned",
     *   title="v4_bible_verses.all",
     *   @OA\Xml(name="v4_bible_verses.all"),
     *   @OA\Property(
     *    property="data",
     *    type="array",
     *    @OA\Items(
     *      @OA\Property(property="verse_start",           ref="#/components/schemas/BibleFile/properties/verse_start"),
     *      @OA\Property(property="verse_end",             ref="#/components/schemas/BibleFile/properties/verse_end"),
     *      @OA\Property(property="verse_sequence",        ref="#/components/schemas/BibleFile/properties/verse_sequence"),
     *      @OA\Property(property="chapter",               ref="#/components/schemas/BibleVerse/properties/chapter"),
     *      @OA\Property(property="book_id",               ref="#/components/schemas/BibleVerse/properties/book_id"),
     *      @OA\Property(property="language_id",           ref="#/components/schemas/Language/properties/id"),
     *      @OA\Property(property="bible_id",              ref="#/components/schemas/Bible/properties/id"),
     *      @OA\Property(property="verse_text",            ref="#/components/schemas/BibleVerse/properties/verse_text"),
     *      @OA\Property(property="fileset_id",            ref="#/components/schemas/Language/properties/iso"),
     *      @OA\Property(property="fileset_set_type_code", ref="#/components/schemas/Bible/properties/date"),
     *      @OA\Property(property="bible_filesets",        ref="#/components/schemas/BibleFileset"),
     *    ),
     *    @OA\Property(property="meta",ref="#/components/schemas/pagination")
     *   )
     * )
     *
     *
     * @param BibleVerse $bible
     *
     * @return array
     */
    public function transform(BibleVerse $bible_verse)
    {
        /**
         * schema=v4_bible_verses.all
         */
        return [
            'verse_start' => $bible_verse->verse_start,
            'verse_end'=> $bible_verse->verse_end,
            'verse_sequence'=> $bible_verse->verse_sequence,
            'chapter'=> $bible_verse->chapter,
            'book_id'=> $bible_verse->book_id,
            'language_id'=> $bible_verse->language_id,
            'bible_id'=> $bible_verse->bible_id,
            'verse_text'=> $bible_verse->verse_text,
            'fileset_id'=> $bible_verse->fileset_id,
            'fileset_set_type_code'=> $bible_verse->fileset_set_type_code,
            'fileset_set_size_code'=> $bible_verse->fileset_set_size_code,
            'bible_filesets' =>$bible_verse->fileset->bible->first()
                ->filesetsWithoutMeta
                ->map(function (BibleFileset $fileset) {
                    return [
                        'id' => $fileset->id,
                        'asset_id' => $fileset->asset_id,
                        'set_type_code' => $fileset->set_type_code,
                        'set_size_code' => $fileset->set_size_code,
                    ];
                })
        ];
    }
}
