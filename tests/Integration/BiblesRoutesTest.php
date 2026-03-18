<?php

namespace Tests\Integration;

use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileset;
use App\Traits\AccessControlAPI;

use Illuminate\Support\Arr;

class BiblesRoutesTest extends ApiV4Test
{
    use AccessControlAPI;


    /**
     * @category V4_API
     * @category Route Name: v4_filesets.types
     * @category Route Path: https://api.dbp.test/bibles/filesets/media/types?v=4&key={key}
     * @see      \App\Http\Controllers\Bible\BibleFileSetsController::mediaTypes
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleFilesetsTypes()
    {
        $path = route('v4_filesets.types', $this->params);
        echo "\nTesting: $path";

        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();
    }

    /**
     * @category V4_API
     * @category Route Name: v4_filesets.download
     * @category Route Path: https://api.dbp.test/bibles/filesets/{fileset_id}/download?v=4&key={key}
     * @see      \App\Http\Controllers\Bible\BibleFileSetsController::download
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleFilesetsDownload()
    {
        $this->markTestIncomplete('Awaiting Fileset download zips');
        $path = route('v4_filesets.download', $this->params);
        echo "\nTesting: $path";

        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_bible_filesets.copyright
     * @category Route Path: https://api.dbp.test/bibles/filesets/ENGESV/copyright?v=4&key={key}
     * @see      \App\Http\Controllers\Bible\BibleFileSetsController::copyright
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleFilesetsCopyright()
    {
        $params = array_merge(['fileset_id' => 'UBUANDP2DA','type' => 'audio_drama'], $this->params);
        $path = route('v4_internal_bible_filesets.copyright', $params);
        echo "\nTesting: $path";

        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();

        // CopyrightTransformer uses ArraySerializer — response is the payload directly, no 'data' wrapper.
        $payload = json_decode($response->getContent(), true) ?? [];
        $this->assertArrayNotHasKey(
            'segmentation_type',
            $payload,
            'Default copyright response must not contain segmentation_type'
        );
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_bible_filesets.copyright
     * @category Route Path: https://api.dbp.test/bibles/filesets/{fileset_id}/copyright?v=4&key={key}&verify_segmentation=true
     * @see      \App\Http\Controllers\Bible\BibleFileSetsController::copyright
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleFilesetsCopyrightWithVerifySegmentation()
    {
        $fileset = BibleFileset::whereNotNull('segmentation_type')
            ->where('hidden', 0)
            ->where('archived', 0)
            ->inRandomOrder()
            ->first();

        if (!$fileset) {
            $this->markTestSkipped('No fileset with non-null segmentation_type seeded in this environment.');
        }

        $params = array_merge(
            ['fileset_id' => $fileset->id, 'verify_segmentation' => 'true'],
            $this->params
        );
        $path = route('v4_internal_bible_filesets.copyright', $params);
        echo "\nTesting: $path";

        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();

        // CopyrightTransformer uses ArraySerializer — response is the payload directly, no 'data' wrapper.
        $payload = json_decode($response->getContent(), true) ?? [];
        $this->assertArrayHasKey(
            'segmentation_type',
            $payload,
            'verify_segmentation=true must include segmentation_type key in copyright response'
        );
        $this->assertContains(
            $payload['segmentation_type'],
            ['section', 'chapter', null],
            'segmentation_type must be section, chapter, or null'
        );
    }

    /**
     * @category V4_API
     * @category Route Name: v4_filesets.books
     * @category Route Path: https://api.dbp.test/bibles/filesets/ENGESV/books?v=4&key={key}&fileset_type=text_plain
     * @see      \App\Http\Controllers\Bible\BooksController::show
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleFilesetsBooks()
    {
        $params = array_merge(['fileset_id' => 'ENGESV', 'fileset_type' => 'text_plain'], $this->params);
        $path = route('v4_filesets.books', $params);
        echo "\nTesting: $path";

        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();
    }


    /**
     * @category V4_API
     * @category Route Name: v4_filesets.show
     * @category Route Path: https://api.dbp.test/bibles/filesets/ENGESV?v=4&key={key}&type=text_plain&bucket=dbp-prod
     * @see      \App\Http\Controllers\Bible\BibleFileSetsController::show
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('non-travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleFilesetsShow()
    {
        $access_control = $this->accessControl($this->key);
        $file = BibleFile::with('fileset')->whereIn('hash_id', $access_control->identifiers)->inRandomOrder()->first();

        $path = route('v4_filesets.show', array_merge([
            'fileset_id' => $file->fileset->id,
            'book_id'    => $file->book_id,
            'chapter'    => $file->chapter_start,
            'type'       => $file->fileset->set_type_code,
            'bucket'     => $file->fileset->asset_id
        ], $this->params));

        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();
    }

    /**
     * @category V4_API
     * @category Route Name: v4_bible.links
     * @category Route Path: https://api.dbp.test/bibles/links?v=4&key={key}
     * @see      \App\Http\Controllers\Bible\BibleLinksController::index
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleLinks()
    {
        $path = route('v4_bible.links', Arr::add($this->params, 'iso', 'eng'));
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();
    }

    /**
     * @category V4_API
     * @category Route Name: v4_bible_books_all
     * @category Route Path: https://api.dbp.test/bibles/books/?v=4&key={key}
     * @see      \App\Http\Controllers\Bible\BooksController::index
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleBooksAll()
    {
        $path = route('v4_bible_books_all', $this->params);
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();
    }

    /**
     * @category V4_API
     * @category Route Name: v4_bible.books
     * @category Route Path: https://api.dbp.test/bibles/ENGESV/book?v=4&key={key}
     * @see      \App\Http\Controllers\Bible\BiblesController::books
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleBooks()
    {
        $path = route('v4_bible.books', array_merge(['bible_id' => 'ENGESV', 'book' => 'MAT'], $this->params));
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();
    }

    /**
     * @category V4_API
     * @category Route Name: v4_bible.books
     * @category Route Path: https://api.dbp.test/bibles/ENGESV/book?v=4&key={key}&verify_content=true
     * @see      \App\Http\Controllers\Bible\BiblesController::books
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleBooksVerifyContentContentTypesIsArray()
    {
        $path = route(
            'v4_bible.books',
            array_merge(['bible_id' => 'ENGESV', 'verify_content' => true], $this->params)
        );
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();

        $books = json_decode($response->getContent(), true)['data'] ?? [];

        foreach ($books as $book) {
            if (!isset($book['content_types'])) {
                continue;
            }

            $content_types = $book['content_types'];

            // content_types must be a sequential array, not an associative map
            $this->assertIsArray($content_types, "content_types for {$book['book_id']} should be an array");
            $this->assertEquals(
                array_values($content_types),
                $content_types,
                "content_types for {$book['book_id']} should have sequential keys (not a map)"
            );

            // content_types should contain no duplicates
            $this->assertCount(
                count(array_unique($content_types)),
                $content_types,
                "content_types for {$book['book_id']} should not contain duplicates"
            );
        }
    }

    /**
     * @category V4_API
     * @category Route Name: v4_bible.archival
     * @category Route Path: https://api.dbp.test/bibles/archival?v=4&key={key}
     * @see      \App\Http\Controllers\Bible\BiblesController::archival
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleArchival()
    {
        $path = route('v4_bible.archival', $this->params);
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();
    }

    /**
     * @category V4_API
     * @category Route Name: v4_bible.one
     * @category Route Path: https://api.dbp.test/bibles/{bible_id}?v=4&key={key}
     * @see      \App\Http\Controllers\Bible\BiblesController::show
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleOne()
    {
        $path = route('v4_bible.one', Arr::add($this->params, 'bible_id', 'ENGESV'));
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();

        $decoded = json_decode($response->getContent(), true);
        $payload = is_array($decoded) ? ($decoded['data'] ?? []) : [];
        foreach ($payload['filesets'] ?? [] as $asset_group) {
            foreach ($asset_group as $fileset) {
                $this->assertArrayNotHasKey(
                    'segmentation_type',
                    $fileset,
                    "Default v4_bible.one response must not contain segmentation_type for fileset {$fileset['id']}"
                );
            }
        }
    }

    /**
     * @category V4_API
     * @category Route Name: v4_bible.one
     * @category Route Path: https://api.dbp.test/bibles/{bible_id}?v=4&key={key}&verify_segmentation=true
     * @see      \App\Http\Controllers\Bible\BiblesController::show
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleOneWithVerifySegmentation()
    {
        // Discover an accessible bible whose filesets carry segmentation_type by hitting v4_bible.all first.
        $index_path = route('v4_bible.all', array_merge(['verify_segmentation' => 'true'], $this->params));
        $index_response = $this->withHeaders($this->params)->get($index_path);
        $index_response->assertSuccessful();
        $index_decoded = json_decode($index_response->getContent(), true);
        $index_payload = is_array($index_decoded) ? ($index_decoded['data'] ?? []) : [];

        $bible_id = null;
        foreach ($index_payload as $bible) {
            foreach ($bible['filesets'] ?? [] as $asset_group) {
                foreach ($asset_group as $fileset) {
                    if (!is_null($fileset['segmentation_type'] ?? null)) {
                        $bible_id = $bible['abbr'];
                        break 3;
                    }
                }
            }
        }

        if (!$bible_id) {
            $this->markTestSkipped('No accessible bible with a fileset carrying non-null segmentation_type in this environment.');
        }

        $path = route('v4_bible.one', array_merge(
            ['bible_id' => $bible_id, 'verify_segmentation' => 'true'],
            $this->params
        ));
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();

        $decoded = json_decode($response->getContent(), true);
        $payload = is_array($decoded) ? ($decoded['data'] ?? []) : [];
        $checked_fileset_count = 0;
        foreach ($payload['filesets'] ?? [] as $asset_group) {
            foreach ($asset_group as $fileset) {
                $this->assertArrayHasKey(
                    'segmentation_type',
                    $fileset,
                    "verify_segmentation=true must include segmentation_type for fileset {$fileset['id']}"
                );
                $this->assertContains(
                    $fileset['segmentation_type'],
                    ['section', 'chapter', null],
                    "segmentation_type must be section, chapter, or null for fileset {$fileset['id']}"
                );
                $checked_fileset_count++;
            }
        }
        $this->assertGreaterThan(0, $checked_fileset_count, 'Expected at least one fileset to verify');
    }

    /**
     * @category V4_API
     * @category Route Name: v4_bible.one
     * @category Route Path: https://api.dbp.test/bibles/{bible_id}?v=4&key={key}&verify_segmentation=true&verify_content=true
     * @see      \App\Http\Controllers\Bible\BiblesController::show
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleOneWithVerseStarts()
    {
        $audio_types = BibleFileset::AUDIO_TYPES;

        // Discover an accessible bible whose filesets carry a section-segmented audio fileset.
        $index_path = route('v4_bible.all', array_merge(['verify_segmentation' => 'true'], $this->params));
        $index_response = $this->withHeaders($this->params)->get($index_path);
        $index_response->assertSuccessful();
        $index_decoded = json_decode($index_response->getContent(), true);
        $index_payload = is_array($index_decoded) ? ($index_decoded['data'] ?? []) : [];

        $bible_id = null;
        foreach ($index_payload as $bible) {
            foreach ($bible['filesets'] ?? [] as $asset_group) {
                foreach ($asset_group as $fileset) {
                    if (
                        ($fileset['segmentation_type'] ?? null) === 'section'
                        && in_array($fileset['type'] ?? null, $audio_types, true)
                    ) {
                        $bible_id = $bible['abbr'];
                        break 3;
                    }
                }
            }
        }

        if (!$bible_id) {
            $this->markTestSkipped('No accessible bible with a section-segmented audio fileset in this environment.');
        }

        // Both flags: per-book verse_starts must appear on qualifying filesets only.
        $path = route('v4_bible.one', array_merge(
            ['bible_id' => $bible_id, 'verify_segmentation' => 'true', 'verify_content' => 'true'],
            $this->params
        ));
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();

        $decoded = json_decode($response->getContent(), true);
        $payload = is_array($decoded) ? ($decoded['data'] ?? []) : [];

        // Top-level filesets map must NOT include verse_starts.
        foreach ($payload['filesets'] ?? [] as $asset_group) {
            foreach ($asset_group as $fileset) {
                $this->assertArrayNotHasKey(
                    'verse_starts',
                    $fileset,
                    "Top-level fileset {$fileset['id']} must not include verse_starts"
                );
            }
        }

        $qualifying_book_filesets = 0;
        foreach ($payload['books'] ?? [] as $book) {
            foreach ($book['filesets'] ?? [] as $book_fileset) {
                $is_qualifying = ($book_fileset['segmentation_type'] ?? null) === 'section'
                    && in_array($book_fileset['type'] ?? null, $audio_types, true);
                if ($is_qualifying) {
                    $this->assertArrayHasKey(
                        'verse_starts',
                        $book_fileset,
                        "Qualifying per-book fileset {$book_fileset['id']} (book {$book['book_id']}) must include verse_starts"
                    );
                    $this->assertIsArray($book_fileset['verse_starts']);
                    $this->assertNotEmpty(
                        $book_fileset['verse_starts'],
                        "verse_starts must contain at least one entry for fileset {$book_fileset['id']} (book {$book['book_id']})"
                    );
                    foreach ($book_fileset['verse_starts'] as $entry) {
                        $this->assertArrayHasKey('chapter_start', $entry);
                        $this->assertArrayHasKey('verse_start', $entry);
                        $this->assertArrayHasKey('verse_start_alt', $entry);
                        $this->assertArrayNotHasKey(
                            'book_id',
                            $entry,
                            "verse_starts entry must not carry book_id (implied by the parent book)"
                        );
                    }
                    $qualifying_book_filesets++;
                } else {
                    $this->assertArrayNotHasKey(
                        'verse_starts',
                        $book_fileset,
                        "Non-qualifying per-book fileset {$book_fileset['id']} (book {$book['book_id']}) must not include verse_starts"
                    );
                }
            }
        }
        $this->assertGreaterThan(0, $qualifying_book_filesets, 'Expected at least one qualifying per-book fileset entry');

        // verify_segmentation alone: verse_starts must be absent everywhere.
        // Cache is flushed between scenarios so each request gets a fresh Bible model
        // (mimics production Memcached, where each request deserializes a fresh copy).
        \Illuminate\Support\Facades\Cache::store('array')->flush();
        $seg_only_path = route('v4_bible.one', array_merge(
            ['bible_id' => $bible_id, 'verify_segmentation' => 'true'],
            $this->params
        ));
        $this->assertNoVerseStartsAnywhere(
            $this->withHeaders($this->params)->get($seg_only_path),
            'verify_segmentation=true alone'
        );

        // verify_content alone: verse_starts must be absent everywhere.
        \Illuminate\Support\Facades\Cache::store('array')->flush();
        $content_only_path = route('v4_bible.one', array_merge(
            ['bible_id' => $bible_id, 'verify_content' => 'true'],
            $this->params
        ));
        $this->assertNoVerseStartsAnywhere(
            $this->withHeaders($this->params)->get($content_only_path),
            'verify_content=true alone'
        );

        // Default request: verse_starts must be absent everywhere.
        \Illuminate\Support\Facades\Cache::store('array')->flush();
        $default_path = route('v4_bible.one', array_merge(['bible_id' => $bible_id], $this->params));
        $this->assertNoVerseStartsAnywhere(
            $this->withHeaders($this->params)->get($default_path),
            'default request'
        );
    }

    private function assertNoVerseStartsAnywhere($response, string $context) : void
    {
        $response->assertSuccessful();
        $decoded = json_decode($response->getContent(), true);
        $payload = is_array($decoded) ? ($decoded['data'] ?? []) : [];
        foreach ($payload['filesets'] ?? [] as $asset_group) {
            foreach ($asset_group as $fileset) {
                $this->assertArrayNotHasKey(
                    'verse_starts',
                    $fileset,
                    "[$context] top-level fileset {$fileset['id']} must not include verse_starts"
                );
            }
        }
        foreach ($payload['books'] ?? [] as $book) {
            foreach ($book['filesets'] ?? [] as $book_fileset) {
                $this->assertArrayNotHasKey(
                    'verse_starts',
                    $book_fileset,
                    "[$context] per-book fileset {$book_fileset['id']} (book {$book['book_id']}) must not include verse_starts"
                );
            }
        }
    }

    /**
     * @category V4_API
     * @category Route Name: v4_bible.all
     * @category Route Path: https://api.dbp.test/bibles?v=4&key={key}
     * @see      \App\Http\Controllers\Bible\BiblesController::index
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleAll()
    {
        $path = route('v4_bible.all', $this->params);
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();

        $decoded = json_decode($response->getContent(), true);
        $payload = is_array($decoded) ? ($decoded['data'] ?? []) : [];
        foreach ($payload as $bible) {
            foreach ($bible['filesets'] ?? [] as $asset_group) {
                foreach ($asset_group as $fileset) {
                    $this->assertArrayNotHasKey(
                        'segmentation_type',
                        $fileset,
                        "Default v4_bible.all response must not contain segmentation_type for fileset {$fileset['id']}"
                    );
                }
            }
        }
    }

    /**
     * @category V4_API
     * @category Route Name: v4_bible.all
     * @category Route Path: https://api.dbp.test/bibles?v=4&key={key}&verify_segmentation=true
     * @see      \App\Http\Controllers\Bible\BiblesController::index
     */
    #[\PHPUnit\Framework\Attributes\Group('BibleRoutes')]
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('travis')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function bibleAllWithVerifySegmentation()
    {
        $path = route('v4_bible.all', array_merge(['verify_segmentation' => 'true'], $this->params));
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();

        $decoded = json_decode($response->getContent(), true);
        $payload = is_array($decoded) ? ($decoded['data'] ?? []) : [];
        $checked_fileset_count = 0;
        foreach ($payload as $bible) {
            foreach ($bible['filesets'] ?? [] as $asset_group) {
                foreach ($asset_group as $fileset) {
                    $this->assertArrayHasKey(
                        'segmentation_type',
                        $fileset,
                        "verify_segmentation=true must include segmentation_type for fileset {$fileset['id']}"
                    );
                    $this->assertContains(
                        $fileset['segmentation_type'],
                        ['section', 'chapter', null],
                        "segmentation_type must be section, chapter, or null for fileset {$fileset['id']}"
                    );
                    $checked_fileset_count++;
                }
            }
        }
        $this->assertGreaterThan(0, $checked_fileset_count, 'Expected at least one fileset to verify');
    }
}
