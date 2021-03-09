<?php

namespace App\Http\Controllers;

use App\Models\Language\Language;
use App\Models\User\Key;

use Illuminate\Http\JsonResponse;
use SoapBox\Formatter\Formatter;
use League\Fractal\Serializer\DataArraySerializer;

use Spatie\Fractalistic\ArraySerializer;
use Spatie\ArrayToXml\ArrayToXml;

use Log;
use Symfony\Component\Yaml\Yaml;
use Yosymfony\Toml\TomlBuilder;

class APIController extends Controller
{
    // Top Level Swagger Docs

    /**
     * @OA\OpenApi(
     *   security={{"dbp_key": {}}},
     *   @OA\Info(
     *     description="Fast, easy, and free API access to video, audio, and text Bibles.",
     *     version="4.0.0-beta",
     *     title="Digital Bible Platform",
     *     termsOfService="http://digitalbibleplatform/terms/",
     *     @OA\Contact(email="support@digitalbibleplatform.com"),
     *     @OA\License(name="Apache 2.0",url="http://www.apache.org/licenses/LICENSE-2.0.html")
     *   ),
     *   @OA\ExternalDocumentation(
     *     description="find more info here",
     *     url="https://faithcomesbyhearing.com/biblebrain"
     *   )
     * )
     * 

     *
     * @OA\Server(
     *     url=API_URL_DOCS,
     *     description="Production Server",
     *     @OA\ServerVariable( serverVariable="schema", enum={"https"}, default="https")
     * )
     *
     *
     * @OA\SecurityScheme(
     *   securityScheme="dbp_key",
     *   type="apiKey",
     *   description="The key granted to the API developer upon sign up",
     *   name="key",
     *   in="query"
     * )
     * @OA\SecurityScheme(
     *   securityScheme="dbp_user_token",
     *   type="apiKey",
     *   description="The token granted to an authenticated end user upon login",
     *   name="api_token",
     *   in="query"
     * )
     * 
     * @OA\Parameter(parameter="version_number",name="v",in="query",description="The Version Number",required=true,@OA\Schema(type="integer",enum={4,2},example=4))
     * @OA\Parameter(parameter="key2",name="key",in="query",description="The key granted to the API developer upon sign up",required=true,@OA\Schema(type="string",example="f4cdf23a-22c3-66c9-cc4f-05dc711b41c6"))
     * @OA\Parameter(parameter="limit", name="limit",  in="query", description="The number of search results to return", @OA\Schema(type="integer",default=25))
     * @OA\Parameter(parameter="page", name="page",  in="query", description="The current page of the results", @OA\Schema(type="integer",default=1))
     * @OA\Parameter(parameter="sort_by", name="sort_by", in="query", description="The field to sort by", @OA\Schema(type="string"))
     * @OA\Parameter(parameter="sort_dir", name="sort_dir", in="query", description="The direction to sort by", @OA\Schema(type="string",enum={"asc","desc"}))
     * @OA\Parameter(name="l10n", in="query", description="When set to a valid three letter language iso, the returning results will be localized in the language matching that iso. (If an applicable translation exists). For a complete list see the `iso` field in the `/languages` route",
     *      @OA\Schema(ref="#/components/schemas/Language/properties/iso")),
     *

     * 
     */


     
    /**
     * Pagination
     * @OA\Schema (
     *   type="object",
     *   schema="pagination",
     *   title="Pagination",
     *   description="The pagination meta response.",
     *   @OA\Xml(name="pagination"),
     *   @OA\Property(property="current_page", type="integer"),
     *   @OA\Property(property="first_page_url", type="string"),
     *   @OA\Property(property="from", type="integer"),
     *   @OA\Property(property="last_page", type="integer"),
     *   @OA\Property(property="last_page_url", type="string"),
     *   @OA\Property(property="next_page_url", type="string"),
     *   @OA\Property(property="path", type="string"),
     *   @OA\Property(property="per_page", type="integer"),
     *   @OA\Property(property="prev_page_url", type="string"),
     *   @OA\Property(property="to", type="integer"),
     *   @OA\Property(property="total", type="integer")
     * )
     */

    /**
     * Pagination alternate
     * @OA\Schema (
     *   type="object",
     *   schema="pagination.alternate",
     *   title="Pagination",
     *   description="The alternate pagination meta response.",
     *   @OA\Xml(name="pagination.alternate"),
     *   @OA\Property(property="meta", type="object",
     *     @OA\Property(property="pagination", type="object",
     *       @OA\Property(property="total", type="integer"),
     *       @OA\Property(property="count", type="integer"),
     *       @OA\Property(property="per_page", type="integer"),
     *       @OA\Property(property="current_page", type="integer"),
     *       @OA\Property(property="total_pages", type="integer"),
     *       @OA\Property(property="links", type="object",
     *        @OA\Property(property="prev", type="string"),
     *        @OA\Property(property="next", type="string")
     *       )
     *    )
     *   )
     * )
     */

    /**
     * Version 2 Tags
     *
     * @OA\Tag(name="Library Audio",    description="v2 These methods retrieve all the information needed to build and retrieve audio information for each chapter/book/or volume.")
     * @OA\Tag(name="Library Catalog",  description="v2 These methods retrieve all the information needed to build and retrieve audio information for each chapter/book/or volume.")
     * @OA\Tag(name="Library Text",     description="v2 These methods allow the caller to retrieve Bible text in a variety of configurations.")
     * @OA\Tag(name="Library Video",    description="v2 These calls address the information needed to build and retrieve video information for each volume.")
     * @OA\Tag(name="Country Language", description="v2 These calls provide all information pertaining to country languages.")
     * @OA\Tag(name="Study Programs",   description="v2 These calls provide all information pertaining to Bible study programs.")
     *
     */

    /**
     * Version 4 Tags
     *
     * @OA\Tag(name="Languages",       
     *      description="v4 Routes for obtaining Languages Data",
     *      @OA\ExternalDocumentation(
     *         description="For more info please refer to the Ethnologue Registration Authority",
     *         url="https://www.iso.org/iso-639-language-codes.html"
     *      )
     * )
     * @OA\Tag(name="Countries",       
     *      description="v4 Routes for obtaining Countries Data",
     *      @OA\ExternalDocumentation(
     *         description="For more info please refer to the Iso Registration Authority",
     *         url="https://www.iso.org/iso-3166-country-codes.html"
     *      )
     * )
     * @OA\Tag(name="Bibles",          description="v4 Routes for obtaining Bibles Data")
     * @OA\Tag(name="Audio Timing", description="v4 Routes for obtaining Audio timing information. This data could be used to search audio bibles for a specific term, make karaoke verse & audio readings, or to jump to a specific location in an audio file.")
     * @OA\Tag(name="Users",           description="v4_internal Routes for obtaining Users Data")
     * @OA\Tag(name="Playlists",       description="v4_internal Routes for obtaining Playlists Data")
     * @OA\Tag(name="Plans",           description="v4_internal Routes for obtaining Plans Data")
     *
     */

    /**
     * The statusCode is a http status code. Every variation of this
     * must also be a http status code. There is a full list here
     * https://en.wikipedia.org/wiki/List_of_HTTP_status_codes
     *
     * @var int $statusCode
     */
    protected $statusCode = 200;
    protected $api;
    protected $serializer;
    protected $preset_v;
    protected $v;
    protected $key;
    protected $user;

    public function __construct()
    {
        $version_name = '';
        if (request()->route()) {
            // The api route names start with v2, v3 or v4;
            $version_name = explode('_', request()->route()->getName())[0];
        }

        if ($version_name === 'v2' || $version_name === 'v3' ||  $version_name === 'v4') {
            $this->api = true;
            // If is an api call require the v parameter
            $this->v   = (int) checkParam('v', true, $this->preset_v);
            $this->key = checkParam('key', true);

            $cache_params = [$this->key];
            $keyExists = cacheRemember('keys', $cache_params, now()->addDay(), function () {
                return Key::with('user')->where('key', $this->key)->first();
            });
            $this->user = $keyExists->user ?? null;

            if (!$this->user) {
                abort(401, 'You need to provide a valid API key. To request an api key please email support@digitalbibleplatform.com');
            }

            // i18n
            $i18n = checkParam('i18n') ?? 'eng';

            $cache_params = [strtolower($i18n)];
            $current_language = cacheRemember('selected_api_language', $cache_params, now()->addDay(), function () use ($i18n) {
                $language = Language::where('iso', $i18n)->select(['iso', 'id'])->first();
                return [
                    'i18n_iso' => $language->iso,
                    'i18n_id'  => $language->id
                ];
            });
            $GLOBALS['i18n_iso'] = $current_language['i18n_iso'];
            $GLOBALS['i18n_id']  = $current_language['i18n_id'];

            $this->serializer = (($this->v === 1) || ($this->v === 2) || ($this->v === 3)) ? new ArraySerializer() : new DataArraySerializer();
        }
    }

    /**
     * Set Status Code
     *
     * @param mixed $statusCode
     *
     * @return mixed
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     *
     * Get The object and return it in the format requested via query params.
     *
     * @param       $object
     *
     * @param array $meta
     * @param null  $s3_transaction_id
     *
     * @return mixed
     */
    public function reply($object, array $meta = [], $s3_transaction_id = null)
    {
        if (isset($_GET['echo'])) {
            $object = [$_GET, $object];
        }
        $input  = checkParam('callback|jsonp');
        $format = checkParam('reply|format');

        if (is_a($object, JsonResponse::class)) {
            return $object;
        }

        // Status Code, Headers, Params, Body, Time
        try {
            apiLogs(request(), $this->statusCode, $s3_transaction_id, request()->ip());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return $this->replyFormatter($object, $meta, $format, $input);
    }

    /**
     * @param $message
     * @param $action
     *
     * @return mixed
     */
    public function replyWithError($message, $action = null)
    {
        if (!$this->api) {
            return view('layouts.errors.broken')->with(['message' => $message]);
        }

        try {
            apiLogs(request(), $this->statusCode);
        } catch (\Exception $e) {
            Log::error($e);
        }

        if ((int) $this->v === 2) {
            return [];
        }

        return response()->json(['error' => [
            'message'     => $message,
            'status_code' => $this->statusCode,
            'action'      => $action ?? ''
        ]], $this->statusCode);
    }


    /**
     * @param       $object
     * @param array $meta
     * @param       $format
     * @param       $input
     *
     * @return JsonResponse|\Illuminate\Http\Response
     */
    private function replyFormatter($object, array $meta, $format, $input)
    {
        $object = json_decode(json_encode($object), true);

        switch ($format) {
            case 'jsonp':
                return response()->json($object, $this->statusCode)
                    ->header('Content-Type', 'application/javascript; charset=utf-8')
                    ->setCallback(request()->input('callback'));
            case 'xml':
                $formatter = ArrayToXml::convert($object, [
                    'rootElementName' => $meta['rootElementName'] ?? 'root',
                    '_attributes'     => $meta['rootAttributes'] ?? []
                ], true, 'utf-8');
                return response()->make($formatter, $this->statusCode)
                    ->header('Content-Type', 'application/xml; charset=utf-8');
            case 'yaml':
                $formatter = Yaml::dump($object);
                return response()->make($formatter, $this->statusCode)
                    ->header('Content-Type', 'text/yaml; charset=utf-8');
            case 'toml':
                $tomlBuilder = new TomlBuilder();
                $formatter   = $tomlBuilder->addValue('multiple', $object)->getTomlString();
                return response()->make($formatter, $this->statusCode)
                    ->header('Content-Type', 'text/yaml; charset=utf-8');
            case 'csv':
                $formatter = Formatter::make($object, Formatter::ARR);
                return response()->make($formatter->toCsv(), $this->statusCode)
                    ->header('Content-Type', 'text/csv; charset=utf-8');
            default:
                if (isset($_GET['pretty'])) {
                    return response()->json($object, $this->statusCode, [], JSON_UNESCAPED_UNICODE)
                        ->header('Content-Type', 'application/json; charset=utf-8')->setCallback($input);
                }
                return response()->json($object, $this->statusCode, [], JSON_UNESCAPED_UNICODE)
                    ->header('Content-Type', 'application/json; charset=utf-8')->setCallback($input);
        }
    }
}
