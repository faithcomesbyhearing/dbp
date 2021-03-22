<?php

namespace App\Http\Controllers\Bible;

use App\Http\Controllers\APIController;

use App\Models\Bible\BibleVerse;
use App\Models\Bible\Book;
use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleFileTimestamp;

use App\Traits\CallsBucketsTrait;
use App\Transformers\AudioTransformer;

class AudioController extends APIController
{
    use CallsBucketsTrait;

    /**
     * Available Timestamps
     *
     * @OA\Get(
     *     path="/timestamps",
     *     tags={"Audio Timing"},
     *     summary="Returns Bible Filesets which have audio timestamps",
     *     description="This call returns a list of fileset that have timestamp metadata associated with them. This data could be used to search audio bibles for a specific term, make karaoke verse & audio readings, or to jump to a specific location in an audio file.",
     *     operationId="v4_timestamps",
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible_timestamps"))
     *     )
     * )
     *
     * @OA\Schema (
     *   type="array",
     *   schema="v4_bible_timestamps",
     *   description="The bibles hash returned for timestamps",
     *   title="Bible Timestamps",
     *   @OA\Xml(name="v4_bible.timestamps"),
     *   @OA\Items(
     *       @OA\Property(property="fileset_id", ref="#/components/schemas/BibleFileset/properties/id"),
     *     )
     *   )
     * )
     *
     *
     *
     * @return mixed
     */
    public function availableTimestamps()
    {
        $filesets = cacheRemember('audio_timestamp_filesets', [], now()->addMinutes(80), function () {
            $hashes = BibleFile::has('timestamps')->select('hash_id')->distinct()->get()->values('hash_id');
            $filesets_id = BibleFileset::whereIn('hash_id', $hashes)->select('id as fileset_id')->get();
            return $filesets_id;
        });
        if ($filesets->count() === 0) {
            return $this->setStatusCode(204)->replyWithError('No timestamps are available at this time');
        }
        return $this->reply($filesets);
    }

    /**
     * Returns a List of timestamps for a given Scripture Reference
     *
     * @OA\Get(
     *     path="/timestamps/{fileset_id}/{book}/{chapter}",
     *     tags={"Bibles","Audio Timing"},
     *     summary="Returns audio timestamps for a chapter",
     *     description="This route will return timestamps for a chapter. Note that the fileset id must be available via the path `/timestamps`. At first, only a few filesets may have timestamps metadata applied.",
     *     operationId="v4_timestamps.verse",
     *     @OA\Parameter(name="fileset_id", in="path", description="The specific fileset to return references for", required=true, @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")),
     *     @OA\Parameter(name="book", in="path", required=true, description="The Book ID for which to return timestamps. For a complete list see the `book_id` field in the `/bibles/books` route.", @OA\Schema(ref="#/components/schemas/Book/properties/id")),
     *     @OA\Parameter(name="chapter", in="path", required=true, description="The chapter for which to return timestamps", @OA\Schema(ref="#/components/schemas/BibleFile/properties/chapter_start")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_audio_timestamps"))
     *     )
     * )
     *
     *
     * @return mixed
     */
    public function timestampsByReference($fileset_id_param = null, $book_url_param = null, $chapter_url_param = null)
    {
        // Check Params
        $id       = checkParam('fileset_id|dam_id|id', true, $fileset_id_param);
        $book     = checkParam('book|osis_code', true, $book_url_param);
        $chapter  = checkParam('chapter_id|chapter_number', true, $chapter_url_param);

        // Fetch Fileset & Files
        $fileset = BibleFileset::uniqueFileset($id, 'audio', true)->first();  // BWF suggest removing audio and true. For audio, the filesetid is unique
        //$fileset = BibleFileset::uniqueFileset($id)->first();  
        if (!$fileset) {
            return $this->setStatusCode(404)->replyWithError(trans('api.bible_fileset_errors_404', ['id' => $id]));
        }

        $bible_files = BibleFile::where('hash_id', $fileset->hash_id)
            ->when($book, function ($query) use ($book) {
                return $query->where('book_id', $book);
            })
            ->when($chapter, function ($query) use ($chapter) {
                return $query->where('chapter_start', $chapter);
            })->get();

        // Fetch Timestamps
        $audioTimestamps = BibleFileTimestamp::whereIn('bible_file_id', $bible_files->pluck('id'))->orderBy('verse_start')->get();


        if ($audioTimestamps->isEmpty() && ($fileset->set_type_code === 'audio_stream' || $fileset->set_type_code === 'audio_drama_stream')) {
            $audioTimestamps = [];
            $bible_files = BibleFile::with('streamBandwidth.transportStreamBytes')->where([
                'hash_id' => $fileset->hash_id,
                'book_id' => $book,
            ])
                ->where('chapter_start', '>=', $chapter)
                ->where('chapter_start', '<=', $chapter)
                ->get();
            foreach ($bible_files as $bible_file) {
                $currentBandwidth = $bible_file->streamBandwidth->first();
                foreach ($currentBandwidth->transportStreamBytes as $stream) {
                    if ($stream->timestamp) {
                        $audioTimestamps[] = $stream->timestamp;
                    }
                }
            }
        }

        // Return Response
        return $this->reply(fractal($audioTimestamps, new AudioTransformer(), $this->serializer));
    }


    /**
     * Note: this is very slow. It is not used by bible.is so will be removed from the Public API until we can investigate further
     * Returns a List of timestamps for a given word
     *
     * @OA\Get(
     *     path="/timestamps/search",
     *     summary="Returns audio timestamps for a specific word",
     *     description="This route will search the text for a specific word or phrase and return a collection of timestamps associated with the verse references connected to the term",
     *     operationId="v4_internal_timestamps.tag",
     *     @OA\Parameter(name="audio_fileset_id", in="query", description="The specific audio fileset to return references for", @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")),
     *     @OA\Parameter(name="text_fileset_id", in="query", description="The specific text fileset to return references for", @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")),
     *     @OA\Parameter(name="book_id", in="query", description="The specific book id to return references for.  For a complete list see the `book_id` field in the `/bibles/books` route.", @OA\Schema(ref="#/components/schemas/Book/properties/id")),
     *     @OA\Parameter(name="query", in="query", required=true, description="The tag for which to return timestamps", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_timestamps_tag"))
     *     )
     * )
     *
     *
     * @return mixed
     */
    public function timestampsByTag()
    {
        // Check Params
        $audio_fileset_id = checkParam('audio_fileset_id');
        $text_fileset_id  = checkParam('text_fileset_id');
        $book_id          = checkParam('book_id');
        $query            = checkParam('query', true);

        // Fetch Fileset & Books
        $audio_fileset = BibleFileset::uniqueFileset($audio_fileset_id, 'audio', true)->first();
        if (!$audio_fileset) {
            return $this->setStatusCode(404)->replyWithError('Audio Fileset not found');
        }
        $text_fileset  = BibleFileset::uniqueFileset($text_fileset_id, 'text', true)->first();
        if (!$text_fileset) {
            return $this->setStatusCode(404)->replyWithError('Text Comparison Fileset not found');
        }
        $books = Book::all();

        // Create Sophia Query
        $query  = \DB::connection()->getPdo()->quote('+' . str_replace(' ', ' +', $query));
        $verses = BibleVerse::where('hash_id', $text_fileset->hash_id)
            ->whereRaw(\DB::raw("MATCH (verse_text) AGAINST($query IN NATURAL LANGUAGE MODE)"))
            ->when($book_id, function ($query) use ($book_id) {
                return $query->where('book_id', $book_id);
            })
            ->select(['book_id', 'chapter'])
            ->take(50)
            ->get();

        // Create BibleFile Query
        $bible_files = BibleFile::query();
        $bible_files->where('hash_id', $audio_fileset->hash_id)->has('timestamps')->with('timestamps');
        foreach ($verses as $verse) {
            $current_book = $books->where('id', $verse->book_id)->first();
            $bible_files->orWhere([
                ['book_id', $current_book->id],
                ['chapter_start', $verse->chapter]
            ]);
        }
        $bible_files = $bible_files->limit(100)->get();
        return $this->reply(fractal($bible_files, new AudioTransformer()));
    }
}
