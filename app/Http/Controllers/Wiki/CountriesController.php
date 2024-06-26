<?php

namespace App\Http\Controllers\Wiki;

use App\Http\Controllers\APIController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Models\Country\JoshuaProject;
use App\Models\Country\Country;
use App\Transformers\CountryTransformer;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use Illuminate\Support\Str;

class CountriesController extends APIController
{

    /**
     * Returns Countries
     *
     * @version 4
     * @category v4_countries.all
     *
     * @return mixed $countries string - A JSON string that contains the status code and error messages if applicable.
     *
     * @OA\Get(
     *     path="/countries",
     *     tags={"Countries"},
     *     summary="Returns Countries",
     *     description="Returns the List of Countries",
     *     operationId="v4_countries.all",
     *     @OA\Parameter(
     *          name="l10n",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/iso"),
     *          description="When set to a valid three letter language iso, the returning results will be localized in the language matching that iso. (If an applicable translation exists). For a complete list see the `iso` field in the `/languages` route"
     *     ),
     *     @OA\Parameter(
     *          name="include_languages",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="When set to true, the return will include the major languages used in each country. ",
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(
     *            mediaType="application/json",
     *            @OA\Schema(ref="#/components/schemas/v4_countries.all")
     *         )
     *     )
     * )
     *
     *
     */
    public function index()
    {
        $languages = checkBoolean('include_languages');
        $limit     = (int) (checkParam('limit') ?? 50);
        $limit     = min($limit, 50);
        $page      = checkParam('page') ?? 1;
        $sort_by   = checkParam('sort_by') ?? null;
        $sort_dir  = checkParam('sort_dir') ?? 'asc';

        if (!in_array(Str::lower($sort_dir), ['asc', 'desc'])) {
            $sort_dir = 'asc';
        }

        if ($sort_by) {
            $columns = getColumnListing('countries', 'dbp');

            if (!isset($columns[$sort_by])) {
                return $this
                    ->setStatusCode(HttpResponse::HTTP_BAD_REQUEST)
                    ->replyWithError(trans('api.sort_errors_400'));
            }
        }

        $access_group_ids = getAccessGroups();

        list($limit, $is_bibleis_gideons) = forceBibleisGideonsPagination($this->key, $limit);

        $cache_params = [
            $GLOBALS['i18n_iso'],
            $languages,
            $limit,
            $page,
            $is_bibleis_gideons,
            $sort_by,
            $sort_dir,
            $access_group_ids->toString()
        ];
        $cache_key = generateCacheSafeKey('countries_list', $cache_params);

        $countries = cacheRememberByKey(
            $cache_key,
            now()->addDay(),
            function () use ($languages, $limit, $access_group_ids, $sort_by, $sort_dir) {
                $countries = Country::with('currentTranslation')
                    ->hasFilesetsAvailable()
                    ->when($sort_by, function ($subquery) use($sort_by, $sort_dir) {
                        return $subquery->orderBy($sort_by, $sort_dir);
                    })
                    ->paginate($limit);

                if ($languages) {
                    $countries->load([
                        'languagesFiltered' => function ($query) use ($access_group_ids) {
                            $query
                                ->IsContentAvailable($access_group_ids)
                                ->orderBy('country_language.population', 'desc');
                        },
                    ]);
                }

                $countries_return = fractal(
                    $countries->getCollection(),
                    CountryTransformer::class,
                    $this->serializer
                );

                return $countries_return->paginateWith(new IlluminatePaginatorAdapter($countries));
            }
        );
        return $this->reply($countries);
    }

    /**
     * Returns Countries
     *
     * @version 4
     * @category v4_countries.search
     *
     * @return mixed $countries string - A JSON string that contains the status code and error messages if applicable.
     *
     * @OA\Get(
     *     path="/countries/search/{search_text}",
     *     tags={"Countries"},
     *     summary="Returns Countries",
     *     description="Returns the List of Countries filtered by names",
     *     operationId="v4_countries.search",
     *     @OA\Parameter(
     *          name="search_text",
     *          in="path",
     *          @OA\Schema(ref="#/components/schemas/Country/properties/name", ref="#/components/schemas/Country/properties/iso_a3"),
     *          description="Search countries by name",
     *          required=true
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(
     *            mediaType="application/json"),
     *            @OA\Schema(ref="#/components/schemas/v4_countries.all")
     *         )
     *     )
     * )
     *
     *
     */
    public function search($search_text = '')
    {
        $limit     = (int) (checkParam('limit') ?? 15);
        $limit     = min($limit, 50);
        $page      = checkParam('page') ?? 1;
        $formatted_search = $this->transformQuerySearchText($search_text);
        $formatted_search_cache = str_replace(' ', '', $search_text);

        if ($formatted_search_cache === '' || !$formatted_search_cache || empty($formatted_search)) {
            return $this->setStatusCode(HttpResponse::HTTP_BAD_REQUEST)->replyWithError(trans('api.search_errors_400'));
        }

        $access_group_ids = getAccessGroups();

        $cache_params = [
            $GLOBALS['i18n_iso'],
            $limit,
            $page,
            $formatted_search_cache,
            $access_group_ids->toString()
        ];
        $cache_key = generateCacheSafeKey('countries', $cache_params);

        $countries = cacheRememberByKey($cache_key, now()->addDay(), function () use ($limit, $formatted_search, $access_group_ids) {
            $countries = Country::with('currentTranslation')
                ->filterableByNameOrIso($formatted_search)
                ->whereHas('languages.bibles', function ($query) use ($access_group_ids) {
                    $query->whereHas('filesets', function ($subquery) use ($access_group_ids) {
                        return $subquery->isContentAvailable($access_group_ids);
                    });
                })
                ->paginate($limit);

            $countries_return = fractal(
                $countries->getCollection(),
                CountryTransformer::class,
                $this->serializer
            );
            return $countries_return->paginateWith(new IlluminatePaginatorAdapter($countries));
        });
        return $this->reply($countries);
    }

    /**
     * Returns Joshua Project Country Information
     *
     * @version 4
     * @category v4_countries.jsp
     *
     * @return mixed $countries string - A JSON string that contains the status code and error messages if applicable.
     *
     */
    public function joshuaProjectIndex()
    {
        $joshua_project_countries = cacheRemember('countries_jp', [$GLOBALS['i18n_iso']], now()->addDay(), function () {
            $countries = JoshuaProject::with([
                'country',
                'translations' => function ($query) {
                    $query->where('language_id', $GLOBALS['i18n_id']);
                },
            ])->get();

            return fractal($countries, CountryTransformer::class);
        });
        return $this->reply($joshua_project_countries);
    }

    /**
     * Returns the Specified Country
     *
     * @version 4
     * @category v4_countries.one
     *
     * @OA\Get(
     *     path="/countries/{id}",
     *     tags={"Countries"},
     *     summary="Returns details for a single Country",
     *     description="Returns details for a single Country",
     *     operationId="v4_countries.one",
     *     @OA\ExternalDocumentation(
     *         description="For more information on Country Codes,  please refer to the ISO Registration Authority",
     *         url="https://www.iso.org/iso-3166-country-codes.html"
     *     ),
     *     @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Country/properties/id")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(
     *            mediaType="application/json",
     *            @OA\Schema(ref="#/components/schemas/v4_countries.one")
     *         )
     *     )
     * )
     *
     * @param  string $id
     *
     * @return mixed $countries string - A JSON string that contains the status code and error messages if applicable.
     *
     */
    public function show($id)
    {
        $access_group_ids = getAccessGroups();

        $cache_params = [$id, $GLOBALS['i18n_iso'], $access_group_ids->toString()];
        $country = cacheRemember('countries', $cache_params, now()->addDay(), function () use ($id, $access_group_ids) {
            $country = Country::with(['languagesFiltered' => function ($query) use ($access_group_ids) {
                $query->IsContentAvailable($access_group_ids)
                    ->with('bibles.translations');
            }])
                ->find($id);
            if (!$country) {
                return $this
                    ->setStatusCode(HttpResponse::HTTP_NOT_FOUND)
                    ->replyWithError(trans('api.countries_errors_404', ['id' => $id]));
            }
            return $country;
        });

        if (!is_a($country, Country::class)) {
            return $country;
        }

        $includes = $this->loadWorldFacts($country);
        return $this->reply(fractal($country, new CountryTransformer(), $this->serializer)->parseIncludes($includes));
    }

    private function loadWorldFacts($country)
    {
        $loadedProfiles = [];

        // World Factbook
        $profiles['communications'] = checkParam('communications') ?? 0;
        $profiles['economy']        = checkParam('economy') ?? 0;
        $profiles['energy']         = checkParam('energy') ?? 0;
        $profiles['geography']      = checkParam('geography') ?? 0;
        $profiles['government']     = checkParam('government') ?? 0;
        $profiles['issues']         = checkParam('issues') ?? 0;
        $profiles['people']         = checkParam('people') ?? 0;
        $profiles['ethnicities']    = checkParam('ethnicity') ?? 0;
        $profiles['regions']        = checkParam('regions') ?? 0;
        $profiles['religions']      = checkParam('religions') ?? 0;
        $profiles['transportation'] = checkParam('transportation') ?? 0;
        $profiles['joshuaProject']  = checkParam('joshuaProject') ?? 0;
        foreach ($profiles as $key => $profile) {
            if ($profile !== 0) {
                $country->load($key);
                if ($country->{$key} !== null) {
                    $loadedProfiles[] = $key;
                }
            }
        }
        return $loadedProfiles;
    }
}
