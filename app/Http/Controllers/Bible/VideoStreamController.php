<?php

namespace App\Http\Controllers\Bible;

use App\Http\Controllers\APIController;
use App\Traits\ArclightConnection;
use App\Models\Bible\Book;
use App\Models\Language\LanguageCode;
use Exception;

class VideoStreamController extends APIController
{
    use ArclightConnection;

    // this endpoints retrieve jesus film by bible chapter i.e (book=MAT, chapter=1), not by the arclight chapter_id
    public function jesusFilmGetChapter(
        $iso = null,
        $book_id = null,
        $chapter = null
    ) {
        $book = Book::where('id', $book_id)->select('id_osis')->first();
        if (!$book) {
            return $this->setStatusCode(404)->replyWithError('Book not found');
        }

        $languages = cacheRemember('arclight_languages', [$iso], now()->addDay(), function () use ($iso) {
            $languages = collect(optional($this->fetchArclight('media-languages', false, false, 'iso3=' . $iso))->mediaLanguages);
            $languages = $languages->where('counts.speakerCount.value', $languages->max('counts.speakerCount.value'))->keyBy('iso3')->map(function ($item) {
                return $item->languageId;
            });
            return $languages;
        });
        $has_language = $languages->contains(function ($value, $key) use ($iso) {
            return $key === $iso;
        });
        if (!$has_language) {
            return $this->setStatusCode(404)->replyWithError('No language could be found for the iso code specified');
        }

        $arclight_id = $languages[$iso];
        $media_languages = cacheRemember('arclight_chapters_language_tag', [$arclight_id], now()->addDay(), function () use ($arclight_id) {
            $media_languages = $this->fetchArclight('media-languages/' . $arclight_id);
            return $media_languages;
        });
        $metadataLanguageTag = isset($media_languages->bcp47) ? $media_languages->bcp47 : '';
              
        // We don't cache this portion because streaming url session can expire
        $films = [];
        $verses = $this->getIdReferences();
        foreach ($verses as $verse_key => $verse) {
            if ($verse && isset($verse[$book->id_osis][$chapter])) {
                // the media component has the descriptions and images for the initial content
                $media_component = $this->fetchArclight('media-components/' . $verse_key , $arclight_id, false, 'metadataLanguageTags=' . $metadataLanguageTag . ',en');
                if (isset($media_component->original['error'])) {
                  $arclight_error = $media_component->original['error'];
                  return $this->setStatusCode($arclight_error['status_code'])->replyWithError($arclight_error['message']);
                } 
                // streaming component returns the video and http urls
                $streaming_component = $this->fetchArclight('media-components/' . $verse_key . '/languages/' . $arclight_id, $arclight_id, false);
                if (isset($streaming_component->original['error'])) {
                  $arclight_error = $streaming_component->original['error'];
                  return $this->setStatusCode($arclight_error['status_code'])->replyWithError($arclight_error['message']);
                }
                
                $films[] = (object) [
                    'component_id' => $verse_key, 
                    'verses' => $verse[$book->id_osis][$chapter],
                    'meta' => [
                        'thumbnail' => $media_component->imageUrls->thumbnail,
                        'thumbnail_high' => $media_component->imageUrls->mobileCinematicHigh,
                        'title' => $media_component->title,
                        'shortDescription' => $media_component->shortDescription,
                        'longDescription' => $media_component->longDescription,
                        'file_name' => $streaming_component->streamingUrls->m3u8[0]->url,
                    ],
                ];
            }
        }

        return $this->reply($films);
    }

    /**
     * Description:
     * Display the languages available for jesus films for arclight.
     *
     * @OA\Get(
     *     path="/arclight/jesus-film/languages",
     *     tags={"Arclight"},
     *     summary="Returns detailed metadata for a single Bible arclight",
     *     description="Returns detailed metadata for a single Bible arclight",
     *     operationId="v4_video_jesus_film_languages",
     *     @OA\Parameter(name="show_detail",in="query"),
     *     @OA\Parameter(name="metadata_tag",in="query"),
     *     @OA\Parameter(name="iso",in="query"),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     *
     * @param  string $id
     *
     * @return \Illuminate\Http\Response
     */
    public function jesusFilmsLanguages()
    {
        try {
            $show_detail = checkBoolean('show_detail');
            $iso  = checkParam('iso') ?? false;
            $language_code = false;

            if ($iso) {
                $language_code = optional(
                    LanguageCode::whereHas('language', function ($query) use ($iso) {
                        $query->where('iso', $iso);
                    })
                    ->where('source', 'arclight')
                    ->select('code')
                    ->first()
                )->code;

                if (!$language_code) {
                    return $this->setStatusCode(404)->replyWithError(trans('api.languages_errors_404'));
                }
            }

            $cache_params = [$show_detail, $language_code, $iso];

            $languages = cacheRemember(
                'arclight_languages_detail',
                $cache_params,
                now()->addDay(),
                function () use ($language_code, $show_detail) {
                    $parameters = $language_code ? 'ids='.$language_code : '';

                    $languages =  $this->fetchArclight(
                        'media-languages',
                        false,
                        false,
                        $parameters
                    );

                    if (isset($languages->original, $languages->original['error'])) {
                        $arclight_error = $languages->original['error'];
                        return $this
                            ->setStatusCode($arclight_error['status_code'])
                            ->replyWithError($arclight_error['message']);
                    }
                    $languages_collection = collect(optional($languages)->mediaLanguages);

                    if (!$show_detail) {
                        return $languages_collection
                            ->pluck('languageId', 'iso3')
                            ->toArray();
                    }

                    return $languages_collection->map(function ($language) {
                        return [
                            'jesus_film_id' => $language->languageId,
                            'iso' => $language->iso3,
                            'name' => $language->name,
                            'autonym' => $language->nameNative
                        ];
                    })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
                }
            );

            return $languages;
        } catch (Exception $e) {
            return [];
        }
    }

    public function jesusFilmChapters($iso = null)
    {
        try {
            $iso = checkParam('iso') ?? $iso;

            if ($iso) {
                $languages = cacheRemember('arclight_languages', [$iso], now()->addDay(), function () use ($iso) {
                    $languages = collect($this->fetchArclight('media-languages', false, false, 'iso3=' . $iso)->mediaLanguages);
                    $languages = $languages->where('counts.speakerCount.value', $languages->max('counts.speakerCount.value'))->keyBy('iso3')->map(function ($item) {
                        return $item->languageId;
                    });
                    return $languages;
                });

                $has_language = $languages->contains(function ($value, $key) use ($iso) {
                    return $key === $iso;
                });

                if (!$has_language) {
                    return $this->setStatusCode(404)->replyWithError('No language could be found for the iso code specified');
                }
                $arclight_id = $languages[$iso];
            } else {
                $arclight_id = checkParam('arclight_id', true);
            }

            $component = cacheRemember('arclight_chapters', [$arclight_id], now()->addDay(), function () use ($arclight_id) {
                $component = $this->fetchArclight('media-components/1_jf-0-0/languages/' . $arclight_id);
                return $component;
            });

            if (!$component) {
                return $this->setStatusCode(404)->replyWithError('Jesus Film component not found');
            }

            $media_languages = cacheRemember('arclight_chapters_language_tag', [$arclight_id], now()->addDay(), function () use ($arclight_id) {
                $media_languages = $this->fetchArclight('media-languages/' . $arclight_id);
                return $media_languages;
            });

            $metadataLanguageTag = isset($media_languages->bcp47) ? $media_languages->bcp47 : '';
            $cache_params =  [$arclight_id, $metadataLanguageTag];
            
            $media_components = $this->fetchArclight('media-components', $arclight_id, true, 'metadataLanguageTags=' . $metadataLanguageTag . ',en');
            if (isset($media_components->original, $media_components->original['error'])) {
                $arclight_error = $media_components->original['error'];
                return $this->setStatusCode($arclight_error['status_code'])->replyWithError($arclight_error['message']);
            }
            
            $metadata = collect($media_components->mediaComponents)
                ->map(function ($component) use ($arclight_id) {
                    return [
                        'mediaComponentId' => $component->mediaComponentId,
                        'meta' => [
                            'thumbnail' => $component->imageUrls->thumbnail,
                            'thumbnail_high' => $component->imageUrls->mobileCinematicHigh,
                            'title' => $component->title,
                            'shortDescription' => $component->shortDescription,
                            'longDescription' => $component->longDescription,
                            'file_name' => route('v4_video_jesus_film_file', [
                                'chapter_id'  => $component->mediaComponentId,
                                'arclight_id' => $arclight_id,
                                'v'           => $this->v,
                                'key'         => $this->key
                            ])
                        ]
                    ];
                })->pluck('meta', 'mediaComponentId');

            return $this->reply([
                'verses'                   => $this->getIdReferences(),
                'meta'                     => $metadata,
                'duration_in_milliseconds' => $component->lengthInMilliseconds,
                'file_name' => route('v4_video_jesus_film_file', [
                    'chapter_id'  => $component->mediaComponentId,
                    'arclight_id' => $arclight_id,
                    'v'           => $this->v,
                    'key'         => $this->key
                ])
            ]);
        } catch (Exception $e) {
            return $this->reply([
                'verses'                   => (object) [],
                'meta'                     => (object) [],
                'duration_in_milliseconds' => 0,
                'file_name' => ''
            ]);
        }
    }

    public function jesusFilmFile()
    {
        try {
            $language_id  = checkParam('arclight_id', true);
            $chapter_id   = checkParam('chapter_id') ?? '1_jf-0-0';

            $media_components = $this->fetchArclight('media-components/' . $chapter_id . '/languages/' . $language_id, $language_id, false);
            if (isset($media_components->original, $media_components->original['error'])) {
                $arclight_error = $media_components->original['error'];
                return $this->setStatusCode($arclight_error['status_code'])->replyWithError($arclight_error['message']);
            }
            
            $stream_file = file_get_contents($media_components->streamingUrls->m3u8[0]->url);
        } catch (Exception $e) {
            $stream_file = '';
        }

        return response($stream_file, 200, [
            'Content-Disposition' => 'attachment',
            'Content-Type'        => 'application/x-mpegURL'
        ]);
    }
}
