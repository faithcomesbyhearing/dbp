<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bible\Book;
use App\Models\Bible\BibleFileset;
use Illuminate\Support\Str;

class SeedBibleText extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ini_set('memory_limit', '4000M');

        $filesets = BibleFileset::where('set_type_code', 'text_plain')->get();
        $books  = Book::select('id', 'id_usfx')->get()->pluck('id', 'id_usfx');
        unset($books['']);

        $tables = collect(\DB::connection('sophia')->select('SHOW TABLES'))->pluck('Tables_in_sophia');
        foreach ($tables as $table) {
            $this->processTable($table, $filesets, $books);
        }
    }

    private function processTable($table, $filesets, $books)
    {
        if (substr($table, -3) !== 'vpl') {
            return;
        }

        $finished_tables_path = storage_path('finished_sophia_tables.json');
        $finished_tables = json_decode(file_get_contents($finished_tables_path));

        if (in_array($table, $finished_tables)) {
            return;
        }

        $fileset = $filesets->where('id', substr($table, 0, -4))->first();

        if (!$fileset) {
            echo "\n Skipping $table";
            return;
        }

        \DB::connection('sophia')->table($table)->orderBy('canon_order')->chunk(5000, function ($verses) use ($fileset, $books, $table) {
            $this->processVerseChunk($verses, $fileset, $books, $table);
        });

        $finished_tables[] = $table;
        file_put_contents($finished_tables_path, json_encode($finished_tables));
    }

    private function processVerseChunk($verses, $fileset, $books, $table)
    {
        $verse_text_combined = '';
        $verse_number_combined = 0;

        foreach ($verses as $key => $verse) {
            if (!isset($books[$verse->book])) {
                echo "\n Skipping Book". $verse->book;
                continue;
            }

            $verseIsSplit = Str::contains($verse->canon_order, ['a','b','c','d','e']);

            if (!$verseIsSplit) {
                $verse_text_combined = '';
            }

            if (Str::contains($verse->canon_order, ['a'])) {
                $verse_text_combined = $verse->verse_text;
                $verse_number_combined = $verse->verse_start;
                continue;
            } elseif ($verseIsSplit) {
                $verse_text_combined .= $verse->verse_text;

                if (isset($verses[$key + 1]) && $verses[$key + 1]->verse_start === $verse_number_combined) {
                    continue;
                }
            }

            if (!$this->insertVerse($fileset, $books, $verse, $verse_text_combined, $table)) {
                break;
            }
        }
    }

    private function insertVerse($fileset, $books, $verse, $verse_text_combined, $table)
    {
        if (\DB::connection('dbp')->table('bible_verses')->where([
            'hash_id'     => $fileset->hash_id,
            'book_id'     => $books[$verse->book],
            'chapter'     => $verse->chapter,
            'verse_start' => $verse->verse_start,
        ])->exists()) {
            $skipped_tables_path = storage_path('skipped_sophia_tables.json');
            $skipped_tables = file_exists($skipped_tables_path) ? json_decode(file_get_contents($skipped_tables_path), true) ?? [] : [];
            $skipped_tables[] = $table;
            file_put_contents($skipped_tables_path, json_encode($skipped_tables));
            return false;
        }

        \DB::connection('dbp')->table('bible_verses')->insert([
            'hash_id'     => $fileset->hash_id,
            'book_id'     => $books[$verse->book],
            'chapter'     => $verse->chapter,
            'verse_start' => $verse->verse_start,
            'verse_end'   => $verse->verse_end,
            'verse_text'  => ($verse_text_combined !== '') ? $verse_text_combined : $verse->verse_text
        ]);

        return true;
    }
}
