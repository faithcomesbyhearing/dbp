<?php

namespace App\Http\Controllers\Wiki;

use App\Http\Controllers\APIController;

use App\Models\Language\NumeralSystem;
use App\Models\Language\NumeralSystemGlyph;
use App\Models\Language\Language;
use App\Models\Bible\Bible;
use App\Transformers\NumbersTransformer;

class NumbersController extends APIController
{

    /**
     *
     * @OA\Get(
     *     path="/numbers/range",
     *     tags={"Languages"},
     *     summary="Return a range of vernacular numbers",
     *     description="This route returns the vernacular numbers for a set range.",
     *     operationId="v4_numbers.range",
     *     @OA\Parameter(
     *          name="script_id",
     *          in="query",
     *          required=true,
     *          description="The script_id to return numbers for",
     *          @OA\Schema(ref="#/components/schemas/NumeralSystem/properties/id")
     *     ),
     *     @OA\Parameter(
     *          name="start",
     *          in="query",
     *          required=true,
     *          description="The start of the range to select for",
     *          @OA\Schema(type="integer"),
     *          example=1
     *     ),
     *     @OA\Parameter(
     *          name="end",
     *          in="query",
     *          required=true,
     *          description="The end of the range to select for",
     *          @OA\Schema(type="integer"),
     *          example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_numbers_range"))
     *     )
     * )
     *
     * @return mixed
     *
     * @OA\Schema (
     *     type="object",
     *     schema="v4_numbers_range",
     *     description="The numbers range return",
     *     title="The numbers range return",
     *     @OA\Xml(name="v4_numbers_range"),
     *     @OA\Property(property="data", type="array",
     *      @OA\Items(
     *        @OA\Property(property="numeral", type="string", example="1"),
     *        @OA\Property(property="numeral_vernacular", type="string", example="১")
     *      )
     *     )
     * )
     *
     */
    public function customRange()
    {
        $script = checkParam('script|script_id');
        $language_code = checkParam('language_code');
        $start  = checkParam('start') ?? 0;
        $end    = checkParam('end');
        if (($end - $start) > 200) {
            return $this->replyWithError(trans('api.numerals_range_error', ['num' => $end]));
        }

        if ($language_code) {
            $language = Language::select(
                [
                    'languages.id',
                    'languages.iso2B',
                    'languages.iso',
                    'language_codes_v2.id as code',
                    'language_codes_v2.language_ISO_639_3_id as isov2',
                    'language_codes_v2.name as name_v2',
                    'language_codes_v2.english_name as english_name_v2'
                ]
            )
            ->leftJoin('language_codes_v2', function ($join_codes_v2) {
                $join_codes_v2->on('language_codes_v2.language_ISO_639_3_id', 'languages.iso');
            })
            ->where(function ($query) use ($language_code) {
                return $query
                    ->where('language_codes_v2.id', $language_code)
                    ->orWhere('languages.iso', $language_code);
            })
            ->with('alphabets')
            ->first();

            if (!empty($language)) {
                if (!empty($language->alphabets)) {
                    $alphabet = $language->alphabets->first();
                    $alphabet_numeral = isset($alphabet->numerals) ? $alphabet->numerals->first() : null;
                    $script = !empty($alphabet_numeral) ? $alphabet_numeral->numeral_system_id : $script;
                }

                if (empty($script)) {
                    $bible = Bible::select('numeral_system_id')
                        ->where('language_id', $language->id)
                        ->whereNotNull('numeral_system_id')
                        ->first();
                    
                    if (!empty($bible)) {
                        $script = $bible->numeral_system_id;
                    }
                }
            }
        }
        // Fetch Numbers By Iso Or Script Code
        $numbers = NumeralSystemGlyph::where('numeral_system_id', $script)
            ->where('value', '>=', $start)
            ->where('value', '<=', $end)->select('value as numeral', 'glyph as numeral_vernacular')->get();

        $formatted_numbers = [];

        if ($this->v === 2 || $this->v === 3) {
            foreach ($numbers as $number) {
                $formatted_numbers["num_$number->numeral"] = $number->numeral_vernacular ?? '';
            }
            return $this->reply([$formatted_numbers]);
        }

        return $this->reply($numbers);
    }

    /**
     *
     * @OA\Get(
     *     path="/numbers",
     *     tags={"Languages"},
     *     summary="Return all Alphabets that have a custom number sets",
     *     description="Returns a range of numbers",
     *     operationId="v4_numbers.index",
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json",@OA\Schema(ref="#/components/schemas/v4_numbers.index"))
     *     )
     * )
     *
     * @OA\Schema (
     *     type="object",
     *     schema="v4_numbers.index",
     *     @OA\Property(property="data", type="array",
     *      @OA\Items(
     *        @OA\Property(property="id", type="string", example="bengali"),
     *        @OA\Property(property="description", type="string", example="description for bengali"),
     *        @OA\Property(property="notes", type="string", example="notes for bengali")
     *      )
     *     )
     * )
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function index()
    {
        if (!$this->api) {
            return view('wiki.languages.alphabets.numerals.index');
        }

        $numeral_systems = cacheRemember('v4_numbers_index', [], now()->addDay(), function () {
            $numeral_systems = NumeralSystem::with('alphabets')->get();
            return fractal($numeral_systems, new NumbersTransformer())->serializeWith($this->serializer);
        });

        return $this->reply($numeral_systems);
    }

    /**
     *
     * @OA\Get(
     *     path="/numbers/{id}",
     *     tags={"Languages"},
     *     summary="Return a single custom number set",
     *     description="Returns a range of numbers",
     *     operationId="v4_numbers.show",
     *     @OA\Parameter(name="id", in="path", required=true, description="The NumeralSystem id",
     *          @OA\Schema(ref="#/components/schemas/NumeralSystem/properties/id")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_numbers.show"))
     *     )
     * )
     *
     * @OA\Schema (
     *     type="object",
     *     schema="v4_numbers.show",
     *     @OA\Property(property="data", type="object",
     *      ref="#/components/schemas/NumeralSystem"
     *     )
     * )
     *
     * @param $system
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function show($system)
    {
        if (!$this->api) {
            return view('wiki.languages.alphabets.numerals.show');
        }

        $numerals = NumeralSystem::where('id', $system)->first();
        if (!$numerals) {
            $error_message = trans('api.alphabet_numerals_errors_404', ['script' => $system], $GLOBALS['i18n_iso']);
            return $this->setStatusCode(404)->replyWithError($error_message);
        }

        $numerals = cacheRemember('v4_numbers_show', [$system], now()->addDay(), function () use ($numerals) {
            $numerals->load('alphabets', 'numerals');
            return fractal($numerals, new NumbersTransformer())->serializeWith($this->serializer);
        });

        return $this->reply($numerals);
    }
}
