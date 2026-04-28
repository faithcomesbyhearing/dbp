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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    non-travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @category Route Name: v4_bible.all
     * @category Route Path: https://api.dbp.test/bibles?v=4&key={key}
     * @see      \App\Http\Controllers\Bible\BiblesController::index
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
     * @group    BibleRoutes
     * @group    V4
     * @group    travis
     * @test
     */
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
