<?php

namespace App\Http\Controllers\Bible;

use App\Http\Controllers\APIController;

use App\Models\Bible\Book;
use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileset;

use App\Traits\CallsBucketsTrait;
use App\Transformers\AudioTransformer;

class AudioControllerV2 extends APIController
{
    use CallsBucketsTrait;

    /**
     *
     * Returns an array of signed audio urls
     *
     * @version  4
     * @category v2_audio_path
     * @link     http://api.dbp4.org/audio/path - V4 Access
     * @link     https://api.dbp.test/audio/path?key=1234&v=4&pretty - V4 Test Access
     * @link     https://dbp.test/eng/docs/swagger/gen#/Version_2/v4_alphabets.one - V4 Test Docs
     *
     * @OA\Get(
     *     path="/audio/path",
     *     tags={"Library Audio"},
     *     summary="Returns Audio File path information",
     *     description="This call returns the file path information for audio files within a volume
     *         This information can be used with the response of the /audio/location call to create
     *         a URI to retrieve the audio files.",
     *     operationId="v2_audio_path",
     *     @OA\Parameter(name="dam_id",
     *         in="query",
     *         description="The DAM ID for which to retrieve file path info.",
     *         required=true,
     *         @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")
     *     ),
     *     @OA\Parameter(name="chapter_id",
     *         in="query",
     *         @OA\Schema(ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *         description="If this value is the return will be limited to the provided chapter",
     *     ),
     *     @OA\Parameter(
     *         name="encoding",
     *         in="query",
     *         @OA\Schema(type="string",title="encoding",deprecated=true),
     *         description="The audio encoding format desired (No longer in use as Audio Files default to mp3)."
     *     ),
     *     @OA\Parameter(name="book_id",
     *         in="query",
     *         @OA\Schema(ref="#/components/schemas/Book/properties/id"),
     *         description="The USFM 2.4 book ID. For a complete list see the `book_id` field in the `/bibles/books` route."
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v2_audio_path")),
     *         @OA\MediaType(mediaType="application/xml", @OA\Schema(ref="#/components/schemas/v2_audio_path")),
     *         @OA\MediaType(mediaType="text/csv", @OA\Schema(ref="#/components/schemas/v2_audio_path")),
     *         @OA\MediaType(mediaType="text/x-yaml", @OA\Schema(ref="#/components/schemas/v2_audio_path"))
     *     )
     * )
     *
     * @see https://api.dbp.test/audio/path?key=1234&v=2&dam_id=ABIWBTN1DA&book_id=LUK
     * @return mixed
     * @throws \Exception
     */
    public function index()
    {
        // Check Params
        $fileset_id = checkParam('dam_id', true);
        $book_id    = checkParam('book_id');
        $chapter_id = checkParam('chapter_id');

        $cache_params = [$fileset_id, $book_id, $chapter_id];

        $audioChapters = cacheRemember('audio_index', $cache_params, now()->addDay(), function () use ($fileset_id, $book_id, $chapter_id) {
            // Account for various book ids

            $book_id = optional(Book::where('id_osis', $book_id)->first())->id;

            // Fetch the Fileset
            $hash_id = optional(BibleFileset::uniqueFileset($fileset_id, 'audio', true)->select('hash_id')->first())->hash_id;
            if (!$hash_id) {
                return $this->setStatusCode(404)->replyWithError('No Audio Fileset could be found for: ' . $hash_id);
            }

            // Fetch The files
            $response = BibleFile::with('book', 'bible')->where('hash_id', $hash_id)
                ->when($chapter_id, function ($query) use ($chapter_id) {
                    return $query->where('chapter_start', $chapter_id);
                })->when($book_id, function ($query) use ($book_id) {
                    return $query->where('book_id', $book_id);
                })->orderBy('file_name');

            return $response->get();
        });

        // Transaction id to be passed to signedUrl
        $transaction_id = random_int(0, 10000000);
        foreach ($audioChapters as $key => $audio_chapter) {
            $audioChapters[$key]->file_name = $this->signedUrl('audio/' . $audio_chapter->bible->first()->id . '/' . $fileset_id . '/' . $audio_chapter->file_name, 'dbp-prod', $transaction_id);
        }

        return $this->reply(fractal($audioChapters, new AudioTransformer(), $this->serializer), [], $transaction_id);
    }
    
    /**
     * Old path route for v2 of the API
     *
     * @version 2
     * @category v2_audio_location
     * @link http://api.dbp4.org/location - V2 Access
     * @link https://api.dbp.test/audio/location?key=TEST_KEY&v=4 - V2 Test Access
     *
     * @OA\Get(
     *     path="/audio/location",
     *     tags={"Library Audio"},
     *     summary="Returns Audio Server Information",
     *     description="This route offers information about the media distribution servers and the protocols they support. It is currently depreciated and only remains to account for the possibility that someone might still be using this old method of uri generation",
     *     operationId="v2_audio_location",
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v2_audio_location")),
     *         @OA\MediaType(mediaType="application/xml",  @OA\Schema(ref="#/components/schemas/v2_audio_location")),
     *         @OA\MediaType(mediaType="text/x-yaml",      @OA\Schema(ref="#/components/schemas/v2_audio_location")),
     *         @OA\MediaType(mediaType="text/csv",      @OA\Schema(ref="#/components/schemas/v2_audio_location"))
     *     )
     * )
     *
     * @OA\Schema (
     *     type="array",
     *     schema="v2_audio_location",
     *     description="v2_audio_location",
     *     title="v2_audio_location",
     *     @OA\Xml(name="v2_audio_location"),
     *     @OA\Items(
     *      @OA\Property(property="server",type="string",example="cloud.faithcomesbyhearing.com"),
     *      @OA\Property(property="root_path",type="string",example="/mp3audiobibles2"),
     *      @OA\Property(property="protocol",type="string",example="http"),
     *      @OA\Property(property="CDN",type="string",example="1"),
     *      @OA\Property(property="priority",type="string",example="5")
     *    )
     * )
     *
     * @return array
     *
     */
    public function location()
    {
        return $this->reply([
            [
                'server'    => config('services.cdn.server'),
                'root_path' => '/audio',
                'protocol'  => 'https',
                'CDN'       => '1',
                'priority'  => '5',
            ],
        ]);
    }
}
