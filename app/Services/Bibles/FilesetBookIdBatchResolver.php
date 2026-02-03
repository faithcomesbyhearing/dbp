<?php

namespace App\Services\Bibles;


use Illuminate\Support\Facades\Log;
use App\Models\Bible\BibleFilesetSize;
use App\Models\Bible\BibleVerse;
use App\Models\Bible\Book;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FilesetBookIdBatchResolver
{
    /**
     * Resolve book IDs for multiple filesets in one pass.
     *
     * @param Collection $filesets
     *
     * @return array<string, array>
     */
    public function resolve(Collection $filesets) : array
    {
        if ($filesets->isEmpty()) {
            Log::info("Empty filesets collection");
            return [];
        }

        $fileset_ids = $filesets->pluck('id')->filter()->values();
        if ($fileset_ids->isEmpty()) {
            return [];
        }

        $hash_ids = $filesets->pluck('hash_id')->filter()->unique()->values();
        $plain_hash_ids = $hash_ids->isEmpty()
            ? collect()
            : BibleVerse::whereIn('hash_id', $hash_ids)->distinct()->pluck('hash_id');

        $plain_hash_lookup = array_flip($plain_hash_ids->all());
        $plain_fileset_ids = $filesets
            ->filter(function ($fileset) use ($plain_hash_lookup) {
                return isset($plain_hash_lookup[$fileset->hash_id]);
            })
            ->pluck('id')
            ->values();

        $non_plain_fileset_ids = $fileset_ids->diff($plain_fileset_ids)->values();
        $results = [];

        if ($plain_fileset_ids->isNotEmpty()) {
            $rows = $this->baseQuery()
                ->whereIn('fileset.id', $plain_fileset_ids)
                ->join('bible_verses as verses', 'verses.hash_id', '=', 'fileset.hash_id')
                ->whereColumn('bible_books.book_id', 'verses.book_id')
                ->select(['fileset.id as fileset_id', 'books.id as book_id'])
                ->distinct()
                ->get();
            $this->accumulateResults($results, $rows);
        }

        if ($non_plain_fileset_ids->isNotEmpty()) {
            $rows = $this->baseQuery()
                ->whereIn('fileset.id', $non_plain_fileset_ids)
                ->join('bible_files as files', 'files.hash_id', '=', 'fileset.hash_id')
                ->whereColumn('bible_books.book_id', 'files.book_id')
                ->select(['fileset.id as fileset_id', 'books.id as book_id'])
                ->distinct()
                ->get();
            $this->accumulateResults($results, $rows);
        }

        foreach ($results as $fileset_id => $book_ids) {
            $results[$fileset_id] = array_values(array_unique($book_ids));
        }
        return $results;
    }

    private function baseQuery()
    {
        return DB::connection('dbp')
            ->table('bible_filesets as fileset')
            ->leftJoin(
                'bible_fileset_connections as connection',
                'connection.hash_id',
                'fileset.hash_id'
            )
            ->leftJoin('bibles', 'bibles.id', 'connection.bible_id')
            ->join('bible_books', function ($join) {
                $join->on('bible_books.bible_id', 'bibles.id');
            })
            ->rightJoin('books', 'books.id', 'bible_books.book_id')
            ->where(function ($query) {
                $size_code_expression = new Expression("fileset.set_size_code LIKE CONCAT('%', books.book_testament, '%')");
                $covenant_expression = new Expression("(fileset.set_size_code = '".BibleFilesetSize::SIZE_STORIES."' AND books.book_testament = '".Book::COVENANT_TESTAMENT."')");
                $ap_fallback_expression = new Expression("(books.book_testament = 'AP' AND (fileset.set_size_code = '".BibleFilesetSize::SIZE_COMPLETE."' OR fileset.set_size_code LIKE '%OT%'))");
                $query->orWhereColumn('fileset.set_size_code', '=', 'books.book_testament')
                    ->orWhere('fileset.set_size_code', BibleFilesetSize::SIZE_COMPLETE)
                    ->orWhereRaw($size_code_expression->getValue(DB::connection()->getQueryGrammar()))
                    ->orWhereRaw($covenant_expression->getValue(DB::connection()->getQueryGrammar()));
                $query->orWhereRaw($ap_fallback_expression->getValue(DB::connection()->getQueryGrammar()));
            });
    }

    private function accumulateResults(array &$results, $rows) : void
    {
        foreach ($rows as $row) {
            $fileset_id = $row->fileset_id;
            $results[$fileset_id] = $results[$fileset_id] ?? [];
            $results[$fileset_id][] = $row->book_id;
        }
    }
}
