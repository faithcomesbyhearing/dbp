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
     *     description="Returns a list of the world's known scripts. This route might be useful to you if you'd like to query information about fonts, alphabets, and the world's writing systems. Some fileset returns may not display correctly without a font delivered by these via the `alphabets/{id}` routes.",
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
     * @version  4
     * @category v4_alphabets.one
     *
     * @OA\Get(
     *     path="/alphabets/{id}",
     *     tags={"Languages"},
     *     summary="Return a single Alphabets",
     *     description="Returns a single alphabet along with whatever bibles and languages using it.",
     *     operationId="v4_alphabets.one",
     *     @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The alphabet ID",
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
     * @param string $id
     * @return mixed $alphabets string - A JSON string that contains the status code and error messages if applicable.
     *
     */
    public function show($id)
    {
        $alphabet = cacheRemember('alphabet', [$id], now()->addDay(), function () use ($id) {
            $alphabet = Alphabet::with('fonts', 'languages', 'bibles.currentTranslation')->find($id);
            return fractal($alphabet, AlphabetTransformer::class, $this->serializer);
        });
        if (!$alphabet) {
            return $this->setStatusCode(404)->replyWithError(trans('api.alphabets_errors_404'));
        }
        return $this->reply($alphabet);
    }
}
