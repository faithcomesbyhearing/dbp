<?php

namespace App\Transformers;

use App\Models\Bible\Bible;

use Illuminate\Support\Arr;

class BibleVerseTransformer extends BaseTransformer
{
    /**
     * A Fractal transformer.
     *
     * @param BibleVerse $bible
     *
     * @return array
     */
    public function transform($bible_verse)
    {
        /**
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
         *      @OA\Property(property="chapter",               ref="#/components/schemas/BibleVerse/properties/chapter"),
         *      @OA\Property(property="book_id",               ref="#/components/schemas/BibleVerse/properties/book_id"),
         *      @OA\Property(property="language_id",           ref="#/components/schemas/Language/properties/id"),
         *      @OA\Property(property="bible_id",              ref="#/components/schemas/Bible/properties/id"),
         *      @OA\Property(property="verse_text",            ref="#/components/schemas/BibleVerse/properties/verse_text"),
         *      @OA\Property(property="fileset_id",            ref="#/components/schemas/Language/properties/iso"),
         *      @OA\Property(property="fileset_set_type_code", ref="#/components/schemas/Bible/properties/date"),
         *    ),
         *    @OA\Property(property="meta",ref="#/components/schemas/pagination")
         *   )
         * )
         */
        return [
            'verse_start' => $bible_verse->verse_start,
            'verse_end'=> $bible_verse->verse_end,
            'chapter'=> $bible_verse->chapter,
            'book_id'=> $bible_verse->book_id,
            'language_id'=> $bible_verse->language_id,
            'bible_id'=> $bible_verse->bible_id,
            'verse_text'=> $bible_verse->verse_text,
            'fileset_id'=> $bible_verse->fileset_id,
            'fileset_set_type_code'=> $bible_verse->fileset_set_type_code,
        ];
    }
}
