<?php

namespace App\Http\Controllers\Wiki;

use App\Http\Controllers\APIController;

use App\Models\Language\Language;
use App\Transformers\LanguageTransformer;
use App\Traits\AccessControlAPI;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;

class LanguagesController extends APIController
{
    use AccessControlAPI;

    /**
     * Display a listing of the resource.
     *
     * Fetches the records from the database > passes them through fractal for transforming.
     *
     * @OA\Get(
     *     path="/languages",
     *     tags={"Languages"},
     *     summary="Returns Languages",
     *     description="Returns the List of Languages",
     *     operationId="v4_languages.all",
     *     @OA\Parameter(
     *          name="country",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Country/properties/id"),
     *          description="The ISO Country Code. For a complete list of Country codes,  please refer to the ISO Registration Authority. https://www.iso.org/iso-3166-country-codes.html"
     *     ),
     *     @OA\Parameter(
     *          name="language_code",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/iso"),
     *          description="The iso code to filter languages by. For a complete list see the `iso` field in the `/languages` route"
     *     ),
     *     @OA\Parameter(
     *          name="language_name",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/iso"),
     *          description="The language_name field will filter results by a specific language name"
     *     ),
     *     @OA\Parameter(
     *          name="include_translations",
     *          in="query",
     *           @OA\Schema(type="boolean"),
     *          description="Include the ISO language ids for available translations"
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/l10n"),
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(ref="#/components/parameters/limit"),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_languages.all"))
     *     )
     * )
     * @return \Illuminate\Http\Response
     *
     * @OA\Schema(
     *   schema="v4_languages.all",
     *   type="object",
     *   @OA\Property(property="data", type="array",
     *      @OA\Items(
     *          @OA\Property(property="id",         ref="#/components/schemas/Language/properties/id"),
     *          @OA\Property(property="glotto_id",  ref="#/components/schemas/Language/properties/glotto_id"),
     *          @OA\Property(property="iso",        ref="#/components/schemas/Language/properties/iso"),
     *          @OA\Property(property="name",       ref="#/components/schemas/Language/properties/name"),
     *          @OA\Property(property="autonym",    ref="#/components/schemas/LanguageTranslation/properties/name"),
     *          @OA\Property(property="bibles",     type="integer", example=12),
     *          @OA\Property(property="filesets",   type="integer", example=4),
     *          @OA\Property(property="country_population", ref="#/components/schemas/Language/properties/population"),
     *      )
     *   )
     * )
     */
    public function index()
    {
        if (!$this->api) {
            return view('wiki.languages.index');
        }

        $country               = checkParam('country');
        $code                  = checkParam('code|iso|language_code');
        $include_translations  = checkParam('include_translations|include_alt_names');
        $name                  = checkParam('name|language_name');
        $limit      = checkParam('limit');
        $page       = checkParam('page');

        $access_control = cacheRemember('access_control', [$this->key], now()->addHour(), function () {
            return $this->accessControl($this->key);
        });

        $cache_params = [$this->v,  $country, $code, $GLOBALS['i18n_id'], $name, $include_translations, $access_control->string, $limit, $page];

        $order = $country ? 'country_population.population' : 'ifnull(current_translation.name, languages.name)';
        $order_dir = $country ? 'desc' : 'asc';
        $select_country_population = $country ? 'country_population.population' : 'null';
        $languages = cacheRemember('languages_all', $cache_params, now()->addDay(), function () use ($country, $include_translations, $code, $name, $access_control, $order, $order_dir, $select_country_population, $limit, $page) {
            $languages = Language::includeCurrentTranslation()
                ->includeAutonymTranslation()
                ->includeExtraLanguages(arrayToCommaSeparatedValues($access_control->hashes))
                ->includeExtraLanguageTranslations($include_translations)
                ->includeCountryPopulation($country)
                ->filterableByCountry($country)
                ->filterableByIsoCode($code)
                ->filterableByName($name) 
                ->select([
                    'languages.id',
                    'languages.glotto_id',
                    'languages.iso',
                    'languages.name as backup_name',
                    'current_translation.name as name',
                    'autonym.name as autonym',
                    \DB::raw($select_country_population . ' as country_population')
                ])
                ->with(['bibles' => function ($query) {
                    $query->whereHas('filesets');
                }])
                ->withCount([
                    'filesets'
                ]);

            if ($page) {
                $languages  = $languages->paginate($limit);
                return $this->reply(fractal($languages->getCollection(), LanguageTransformer::class)->paginateWith(new IlluminatePaginatorAdapter($languages)));
            }

            $languages = $languages->limit($limit)->get();
            return fractal($languages, new LanguageTransformer(), $this->serializer);
        });


        return $this->reply($languages);
    }

    /**
     * @param $id
     *
     * @OA\Get(
     *     path="/languages/{id}",
     *     tags={"Languages"},
     *     summary="Returns details on a single Language",
     *     description="Returns details on a single Language",
     *     operationId="v4_languages.one",
     *     @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The language ID",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Language/properties/id")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_languages.one"))
     *     )
     * )
     *
     *
     * @OA\Schema(
     *   schema="v4_languages.one",
     *   type="object",
     *   @OA\Property(property="data", type="object",
     *      ref="#/components/schemas/Language"
     *   )
     * )
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function show($id)
    {
        $access_control = cacheRemember('access_control', [$this->key], now()->addHour(), function () {
            return $this->accessControl($this->key);
        });
        $cache_params = [$id, $access_control->string];
        $language = cacheRemember('language', $cache_params, now()->addDay(), function () use ($id, $access_control) {
            $language = Language::where('id', $id)->orWhere('iso', $id)
                ->includeExtraLanguages(arrayToCommaSeparatedValues($access_control->hashes), false)
                ->first();
            if (!$language) {
                return $this->setStatusCode(404)->replyWithError("Language not found for ID: $id");
            }
            $language->load(
                'translations',
                'codes',
                'dialects',
                'classifications',
                'countries',
                'primaryCountry',
                'bibles.translations.language',
                'bibles.filesets',
                'resources.translations',
                'resources.links'
            );
            return fractal($language, new LanguageTransformer());
        });

        return $this->reply($language);
    }

    public function valid($id)
    {
        $language = cacheRemember('language_single_valid', [$id], now()->addDay(), function () use ($id) {
            return Language::where('iso', $id)->exists();
        });

        return $this->reply($language);
    }
}
