<?php

namespace App\Transformers;

class FontsTransformer extends BaseTransformer
{
    /**
     * A Fractal transformer.
     *
     * @param $font
     *
     * @return array
     * @OA\Schema (
     *     type="object",
     *     schema="font_response",
     *     description="The full alphabet return for the single alphabet route",
     *     title="The single alphabet response",
     *     @OA\Xml(name="v4_alphabets_one_response"),
     *     @OA\Property(property="id",                     ref="#/components/schemas/AlphabetFont/properties/id"),
     *     @OA\Property(property="name",                   ref="#/components/schemas/AlphabetFont/properties/font_filename"),
     *     @OA\Property(property="base_url",               type="string"),
     *     @OA\Property(property="files",                  type="object",
     *          @OA\Property(property="zip",               type="string"),
     *          @OA\Property(property="svg",               type="string"),
     *          @OA\Property(property="ttf",               type="string"),
     *          @OA\Property(property="platforms",         type="object",
     *             @OA\Property(property="android",           type="boolean"),
     *             @OA\Property(property="ios",               type="boolean"),
     *             @OA\Property(property="web",               type="boolean"),
     *       )
     *     )
     * )
     */
    public function transform($font)
    {
        $font_server = config('services.cdn.fonts_server');
        $base_url = "https://$font_server/fonts/";

        return [
            'id'       => $font->id,
            'name'     => $font->font_name,
            'base_url' => $base_url . $font->font_filename . '.ttf',
            'files'    => [
                'zip'       => $base_url . $font->font_filename . '.zip',
                'svg'       => $base_url . $font->font_filename . '.svg',
                'ttf'       => $base_url . $font->font_filename . '.ttf',
                'platforms' => [
                       'android' => true,
                       'ios'     => true,
                       'web'     => true
                   ]
            ]
        ];
    }
}
