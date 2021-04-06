<?php

namespace App\Http\Controllers\Wiki;

use App\Http\Controllers\APIController;

use App\Models\Language\Alphabet;
use App\Transformers\AlphabetTransformer;

class AlphabetsController extends APIController
{

    /**
     * Returns Alphabets
     *
     * @version 4
     * @category v4_alphabets.all
     *
     * @OA\Get(
     *     path="/alphabets",
     *     tags={"Languages"},
     *     summary="Returns Alphabets",
     *     description="Returns a list of the world's known scripts. This route might be useful to you if you'd like to query information about fonts, alphabets, and the world's writing systems. Some fileset returns may not display correctly without a font delivered by these via the `alphabets/{script_id}` routes.",
     *     operationId="v4_alphabets.all",
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_alphabets_all_response"))
     *     )
     * )
     *
     * @return mixed $alphabets string - A JSON string that contains the status code and error messages if applicable.
     *
     */
    public function index()
    {
        $alphabets = cacheRemember('alphabets', [], now()->addDay(), function () {
            $alphabets = Alphabet::select(['name', 'script', 'family', 'direction', 'type'])->get();
            return fractal($alphabets, new AlphabetTransformer(), $this->serializer);
        });

        return $this->reply($alphabets);
    }


    /**
     * Returns Single Alphabet
     *
     * @OA\Get(
     *     path="/alphabets/{script_id}",
     *     tags={"Languages"},
     *     summary="Return details on a single Alphabet",
     *     description="Returns a single alphabet along with whatever bibles and languages using it.",
     *     operationId="v4_alphabets.one",
     *     @OA\Parameter(
     *          name="script_id",
     *          in="path",
     *          description="The alphabet Script, which is used as the identifier",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Alphabet/properties/script")
     *      ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/v4_alphabets_one_response")
     *         )
     *     )
     * )
     *
     * @param string $script_id
     * @return mixed $alphabets string - A JSON string that contains the status code and error messages if applicable.
     *
     */
    public function show($script_id)
    {
        $alphabet = cacheRemember('alphabet', [$script_id], now()->addDay(), function () use ($script_id) {
            $alphabet = Alphabet::with('fonts', 'languages', 'bibles.currentTranslation')->find($script_id);
            return fractal($alphabet, AlphabetTransformer::class, $this->serializer);
        });
        if (!$alphabet) {
            return $this->setStatusCode(404)->replyWithError(trans('api.alphabets_errors_404'));
        }
        return $this->reply($alphabet);
    }
}
