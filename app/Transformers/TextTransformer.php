<?php

namespace App\Transformers;

class TextTransformer extends BaseTransformer
{
    public function transform($text)
    {
        switch ($this->version) {
            case 2:
            case 3:
                return $this->transformForV2($text);
                break;
            case 4:
            default:
                return $this->transformForV4($text);
        }
    }

    /**
     * @OA\Schema (
     *   type="array",
     *   schema="v2_text_search",
     *   description="The v2_text_search",
     *   title="v2_text_search",
     *   @OA\Xml(name="v2_text_search"),
     *   @OA\Items(
     *     @OA\Property(property="dam_id",     ref="#/components/schemas/Bible/properties/id"),
     *     @OA\Property(property="book_name",  ref="#/components/schemas/Book/properties/name"),
     *     @OA\Property(property="book_id",    ref="#/components/schemas/Book/properties/id_osis"),
     *     @OA\Property(property="chapter_id", ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *     @OA\Property(property="verse_id",   ref="#/components/schemas/BibleFile/properties/verse_start"),
     *     @OA\Property(property="verse_text", ref="#/components/schemas/BibleFile/properties/verse_text"),
     *     @OA\Property(property="book_order", ref="#/components/schemas/Book/properties/protestant_order")
     *     )
     *   )
     * )
     *
     * @OA\Schema (
     *   type="array",
     *   schema="v2_text_search_group",
     *   description="The bible Search Group Response",
     *   title="v2_text_search_group",
     *   @OA\Xml(name="v2_text_search_group"),
     *   @OA\Items(
     *              @OA\Property(property="dam_id",     ref="#/components/schemas/Bible/properties/id"),
     *              @OA\Property(property="book_name",  ref="#/components/schemas/Book/properties/name"),
     *              @OA\Property(property="book_id",    ref="#/components/schemas/Book/properties/id_osis"),
     *              @OA\Property(property="chapter_id", ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *              @OA\Property(property="verse_id",   ref="#/components/schemas/BibleFile/properties/verse_start"),
     *              @OA\Property(property="verse_text", ref="#/components/schemas/BibleFile/properties/verse_text"),
     *              @OA\Property(property="results",    type="integer",minimum=0,example=45),
     *              @OA\Property(property="book_order", ref="#/components/schemas/Book/properties/protestant_order")
     *     )
     *   )
     * )
     *
     * @OA\Schema (
     *   type="array",
     *   schema="v2_text_verse",
     *   description="The bible Search Group Response",
     *   title="v2_text_verse",
     *   @OA\Xml(name="v2_text_verse"),
     *   @OA\Items(
     *              @OA\Property(property="book_name",         ref="#/components/schemas/Book/properties/name"),
     *              @OA\Property(property="book_id",           ref="#/components/schemas/Book/properties/name"),
     *              @OA\Property(property="book_order",           ref="#/components/schemas/Book/properties/protestant_order"),
     *              @OA\Property(property="chapter_id",        ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *              @OA\Property(property="chapter_title",     type="string",example="Chapter 1"),
     *              @OA\Property(property="verse_id",          ref="#/components/schemas/BibleFile/properties/verse_start"),
     *              @OA\Property(property="verse_text",        ref="#/components/schemas/BibleFile/properties/verse_text"),
     *              @OA\Property(property="paragraph_number",  type="string",example="2")
     *     )
     *   )
     * )
     */
    public function transformForV2($text)
    {
        switch ($this->route) {
            /**
             * schema="v2_text_search"
             */
            case 'v2_text_search':
                // This is a temporal fix while v2 transitions to v4
                // It must return a damid longer than six characters, then it is using the request DAMID
                $partial_testament_type = 'P';
                $generalized_testament_types = ['NT', 'OT', 'C'];
                $damid_with_testament = $text->dam_id_request;

                if (strlen($text->dam_id_request) === 6 && in_array($text->book_testament, $generalized_testament_types)) {
                    $testament_initial = substr($text->book_testament, 0, 1);
                    $damid_with_testament = $text->dam_id_request . $testament_initial;
                } else if (strlen($text->dam_id_request) === 6) {
                    $damid_with_testament = $text->dam_id_request . $partial_testament_type;
                }
                
                return [
                    'dam_id' => (string) $damid_with_testament ?? $text->bible_id,
                    'book_name' => (string) $text->book_name,
                    'book_id' => (string) $text->book_id,
                    'chapter_id' => (string) $text->chapter,
                    'verse_id' => (string) $text->verse_start,
                    'verse_text' => (string) $text->verse_text,
                    'book_order' => (string) $text->protestant_order
                ];

            /**
             * schema="v2_text_search_group"
             */
            case 'v2_text_search_group':
                return [
                    'dam_id' => $text->bible_id ?? '',
                    'book_name' => $text->book_name ?? '',
                    'book_id' => $text->book_id ?? '',
                    'chapter_id' => (string) $text->chapter ?? '',
                    'verse_id' => (string) $text->verse_start ?? '',
                    'verse_text' => $text->verse_text ?? '',
                    'results' => (string) $text->resultsCount ?? '',
                    'book_order' => (string) $text->protestant_order ?? ''
                ];



            case 'v2_library_verseInfo':
                // WIP for v2 compatibility
                return [
                    'book_name' => (string) $text->book_id
                ];

            /**
             * schema="v2_text_verse"
             */
            default:
                return [
                    'book_name' => (string) $text->book_name,
                    'book_id' => (string) $text->book_name,
                    'book_order' => (string) $text->protestant_order,
                    'chapter_id' => (string) $text->chapter,
                    'chapter_title' => "Chapter $text->chapter",
                    'verse_id' => (string) $text->verse_start,
                    'verse_text' => (string) $text->verse_text,
                    'paragraph_number' => (string) $text->verse_start,
                ];
        }
    }

    /**
     * @OA\Schema (
     *    type="object",
     *    schema="v4_bible_filesets_chapter",
     *    description="The bible chapter response",
     *    title="v4_bible_filesets_chapter",
     *    @OA\Xml(name="v4_bible_filesets_chapter"),
     *    @OA\Property(property="data", type="array",
     *      @OA\Items(
     *              @OA\Property(property="book_id",           ref="#/components/schemas/Book/properties/id"),
     *              @OA\Property(property="book_name",         ref="#/components/schemas/Book/properties/name"),
     *              @OA\Property(property="book_name_alt",     ref="#/components/schemas/BookTranslation/properties/name"),
     *              @OA\Property(property="chapter",           ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *              @OA\Property(property="chapter_alt",       ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *              @OA\Property(property="verse_start",       ref="#/components/schemas/BibleFile/properties/verse_start"),
     *              @OA\Property(property="verse_start_alt",   ref="#/components/schemas/BibleFile/properties/verse_start"),
     *              @OA\Property(property="verse_end",         ref="#/components/schemas/BibleFile/properties/verse_end"),
     *              @OA\Property(property="verse_end_alt",     ref="#/components/schemas/BibleFile/properties/verse_end"),
     *              @OA\Property(property="verse_text",        type="string")
     *      )
     *     )
     *   )
     * )
     *
     * @param $text
     * @return array
     */
    public function transformForV4($text)
    {
        return [
            'book_id' => $text->book_id ?? '',
            'book_name' => $text->book_name ?? '',
            'book_name_alt' => $text->book_vernacular_name ?? '',
            'chapter' => (int) $text->chapter,
            'chapter_alt' => (string) $text->chapter_vernacular,
            'verse_start' => (int) $text->verse_start,
            'verse_start_alt' => (string) $text->verse_start_vernacular,
            'verse_end' => (int) $text->verse_end,
            'verse_end_alt' => (string) $text->verse_end_vernacular,
            'verse_text' => (string) $text->verse_text
        ];
    }
}
