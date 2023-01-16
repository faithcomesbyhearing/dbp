<?php

namespace App\Transformers;

use App\Models\User\Study\Highlight;
use League\Fractal\TransformerAbstract;

class UserHighlightsTransformer extends TransformerAbstract
{
    /**
     * @OA\Schema (
     *    type="object",
     *    schema="v4_internal_highlights_index",
     *    description="The v4 highlights index response. Note the fileset_id is being used to identify the item instead of the bible_id.
     *    This is important as different filesets may have different numbers for the highlighted words field depending on their revision.",
     *    title="v4_internal_highlights_index",
     *    @OA\Xml(name="v4_internal_highlights_index"),
     *      allOf={
     *        @OA\Schema(ref="#/components/schemas/pagination"),
     *      },
     *    @OA\Property(property="data", type="array",
     *      @OA\Items(
     *              @OA\Property(property="id",                     ref="#/components/schemas/Highlight/properties/id"),
     *              @OA\Property(property="fileset_id",             ref="#/components/schemas/BibleFileset/properties/id"),
     *              @OA\Property(property="book_id",                ref="#/components/schemas/Book/properties/id"),
     *              @OA\Property(property="book_name",              ref="#/components/schemas/BibleBook/properties/name"),
     *              @OA\Property(property="chapter",                ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *              @OA\Property(property="verse_start",            ref="#/components/schemas/BibleFile/properties/verse_start"),
     *              @OA\Property(property="verse_end",              ref="#/components/schemas/BibleFile/properties/verse_end"),
     *              @OA\Property(property="verse_text",             ref="#/components/schemas/BibleFile/properties/verse_text"),
     *              @OA\Property(property="highlight_start",        ref="#/components/schemas/Highlight/properties/highlight_start"),
     *              @OA\Property(property="highlighted_words",      ref="#/components/schemas/Highlight/properties/highlighted_words"),
     *              @OA\Property(property="highlighted_color",      ref="#/components/schemas/Highlight/properties/highlighted_color")
     *           ),
     *     )
     *    )
     *   )
     * )
     * @param Highlight $highlight
     *
     * @return array
     */
    public function transform(Highlight $highlight)
    {
        $this->checkColorPreference($highlight);
        $highlight_fileset_info = $highlight->fileset_info;
        $verse_text = $highlight_fileset_info->get('verse_text');
        $audio_filesets = $highlight_fileset_info->get('audio_filesets');

        return [
            'id'                => (int) $highlight->id,
            'bible_id'          => (string) $highlight->bible_id,
            'book_id'           => (string) $highlight->book_id,
            'book_name'         => (string) optional($highlight->book)->name,
            'chapter'           => (int) $highlight->chapter,
            'verse_start'       => $highlight->verse_sequence,
            'verse_start_alt'   => $highlight->verse_start,
            'verse_end'         => (int) $highlight->verse_end,
            'verse_text'        => (string) $verse_text,
            'highlight_start'   => (int) $highlight->highlight_start,
            'highlighted_words' => $highlight->highlighted_words,
            'highlighted_color' => $highlight->color,
            'tags'              => $highlight->tags,
            'audio_filesets'    => $audio_filesets
        ];
    }

    protected function checkColorPreference($highlight)
    {
        $color_preference = checkParam('prefer_color') ?? 'rgba';
        $highlight->color = Highlight::checkAndReturnColorPreference($highlight, $color_preference);
    }
}
