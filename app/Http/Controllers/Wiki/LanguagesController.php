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
        $show_bibles           = checkBoolean('show_bibles');
        $limit                 = (int) (checkParam('limit') ?? 50);
        $limit                 = min($limit, 150);
        $page                  = checkParam('page') ?? 1;

        // note: this two commented changes can be removed when bibleis and gideons no longer require a non-paginated response
        // remove pagination for bibleis and gideons (temporal fix)
        list($limit, $is_bibleis_gideons) = forceBibleisGideonsPagination($this->key, $limit);
        // instead of returning hashes, accessControl will return language ids associated with the hashes
        $access_control =  $this->accessControl($this->key, 'languages');
        $cache_params = [
            $this->v,  
            $country, 
            $code, 
            $GLOBALS['i18n_id'], 
            $name, 
            $include_translations, 
            $access_control->string, 
            $limit, 
            $page,
            $is_bibleis_gideons,
        ];

        $order = $country ? 'country_population.population' : 'ifnull(current_translation.name, languages.name)';
        $order_dir = $country ? 'desc' : 'asc';
        $select_country_population = $country ? 'country_population.population' : 'null';
        $languages = cacheRemember('languages_all', $cache_params, now()->addDay(), function () use ($country, $include_translations, $code, $name, $access_control, $order, $order_dir, $select_country_population, $limit, $page) {
            $languages = Language::includeCurrentTranslation()
                ->includeAutonymTranslation()
                ->includeExtraLanguages(arrayToCommaSeparatedValues($access_control->identifiers))
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

            $languages = $languages->paginate($limit);
            $languages_return = fractal(
                $languages->getCollection(),
                LanguageTransformer::class,
                $this->serializer
            );

            return $languages_return->paginateWith(new IlluminatePaginatorAdapter($languages));
        });

        return $this->reply($languages);
    }

    /**
     * @param $search_text
     *
     * @OA\Get(
     *     path="/languages/search/{search_text}",
     *     tags={"Languages"},
     *     summary="Returns languages related to this search",
     *     description="Returns paginated languages that have search text in its name or country",
     *     operationId="v4_languages.one",
     *     @OA\Parameter(
     *          name="search_text",
     *          in="path",
     *          description="The language text to search by",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Language/properties/search_text")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_languages.one"))
     *     )
     * )
     *
     * @OA\Schema(
     *   schema="v4_languages.search",
     *   type="object",
     *   @OA\Property(property="data", type="object",
     *      ref="#/components/schemas/Language"
     *   )
     * )
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function search($search_text)
    {
        $page  = checkParam('page') ?? 1;
        $limit = (int) (checkParam('limit') ?? 25);
        $limit = min($limit, 50);
        // instead of returning hashes, accessControl will return language ids associated with the hashes
        $access_control = $this->accessControl($this->key, 'languages');
        $cache_params = [
            $this->v,
            $search_text,
            $access_control->string,
            $limit,
            $page,
        ];

        $languages = cacheRemember('languages_search', $cache_params, now()->addDay(), function () use ($search_text, $access_control, $page, $limit) {
            $languages = Language::includeCurrentTranslation()
                ->includeAutonymTranslation()
                ->leftJoin('countries', 'languages.country_id', 'countries.id')
                ->includeExtraLanguages(arrayToCommaSeparatedValues($access_control->identifiers), false)
                ->filterableByNameOrAutonym($search_text)
                ->select([
                    'languages.id',
                    'languages.glotto_id',
                    'languages.iso',
                    'languages.name as backup_name',
                    'current_translation.name as name',
                    'autonym.name as autonym',
                ]);
            $languages = $languages->paginate($limit);
            $languages_return = fractal(
                $languages->getCollection(),
                LanguageTransformer::class,
                $this->serializer
            );
            return $languages_return->paginateWith(new IlluminatePaginatorAdapter($languages));
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
        // instead of returning hashes, accessControl will return language ids associated with the hashes
        $access_control = $this->accessControl($this->key, 'languages');
        $cache_params = [$id, $access_control->string];
        $language = cacheRemember('language', $cache_params, now()->addDay(), function () use ($id, $access_control) {
            $language = Language::where('id', $id)->orWhere('iso', $id)
                ->includeExtraLanguages(arrayToCommaSeparatedValues($access_control->identifiers))
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
