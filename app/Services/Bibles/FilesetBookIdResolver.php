<?php

namespace App\Services\Bibles;

use App\Models\Bible\BibleFileset;
use Illuminate\Support\Facades\Log;

class FilesetBookIdResolver
{
    /**
     * Resolve book IDs for a single fileset without re-fetching it.
     *
     * @param BibleFileset $fileset
     * @param string $versification
     *
     * @return array
     */
    public function resolve(BibleFileset $fileset) : array
    {
        $batch_resolver = new FilesetBookIdBatchResolver();
        $map = $batch_resolver->resolve(collect([$fileset]));
        $book_ids = $map[$fileset->id] ?? [];
        if (empty($book_ids)) {
            Log::info('Single fileset resolver returned empty map', [
                'fileset_id' => $fileset->id,
                'hash_id' => $fileset->hash_id,
                'set_type_code' => $fileset->set_type_code,
            ]);
        }

        return $book_ids;
    }
}
