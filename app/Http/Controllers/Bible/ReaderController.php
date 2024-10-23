<?php

namespace App\Http\Controllers\Bible;

use App\Http\Controllers\APIController;
use App\Models\Bible\Bible;
use App\Models\Bible\BibleBook;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleVerse;
use App\Models\Bible\Book;
use App\Models\Language\Language;
use App\Models\User\Key;

use App\Traits\AccessControlAPI;

class ReaderController extends APIController
{
    use AccessControlAPI;

    /**
     * The Languages Available View
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function languages()
    {
        $languages = cacheRemember('Bible_is_languages', [], now()->addDay(), function () {
            $project_key = optional(Key::where('name', 'new bible.is mobile app')->first())->key;
            return Language::select(['languages.id', 'languages.name', 'autonym.name as autonym'])
                ->leftJoin('language_translations as autonym', function ($join) {
                    $join->on('autonym.language_source_id', 'languages.id');
                    $join->on('autonym.language_translation_id', 'languages.id');
                    $join->orderBy('autonym.priority', 'desc');
                })
                ->whereHas('filesets', function ($query) use ($project_key) {
                    $query->whereHas('fileset', function ($query) {
                        $query->where('set_type_code', 'text_plain')->where('asset_id', 'dbp-prod');
                    });
                    $query->isContentAvailable($project_key);
                })->withCount('bibles')->get();
        });

        return view('bibles.reader.languages', compact('languages'));
    }

    /**
     * @param $language_id
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function bibles($language_id)
    {
        $filesets = BibleFileset::with('bible.translations')
            ->whereHas('bible', function ($query) use ($language_id) {
                $query->where('language_id', $language_id);
            })
            ->where('asset_id', 'dbp-prod')->where('set_type_code', 'text_plain')->get();

        return view('bibles.reader.bibles', compact('filesets'));
    }

    /**
     *
     * Generates the Book Navigation Menu View for the Bible Fileset
     *
     * @param $bible_id
     *
     * @return \Illuminate\View\View
     */
    public function books($bible_id)
    {
        $bible = Bible::where('id', $bible_id)->first();

        $books = collect();
        $language_id = null;

        if ($bible) {
            $book_included = [];
            $language_id = $bible->language_id;
            $filesets = $bible->filesetTypeTextPlainAssociated();

            foreach ($filesets as $fileset) {
                $sophia_books = BibleVerse::where('hash_id', $fileset->hash_id)->select('book_id')->distinct()->get();
                $books_fileset = Book::whereIn('id', $sophia_books->pluck('book_id')->toArray())->orderBy('protestant_order', 'asc')->get();
                $bible_books = BibleBook::where('bible_id', $bible_id)->whereIn('book_id', $books_fileset->pluck('id')->toArray())->get();

                foreach ($books_fileset as $book) {
                    $currentBook = $bible_books->where('book_id', $book->id)->first();
                    $book->vernacular_title = $currentBook ? $currentBook->name : null;
                    $book->existing_chapters = $currentBook ? $currentBook->chapters : null;
                    if (!isset($book_included[$book->id])) {
                        $books->push($book);
                        $book_included[$book->id] = true;
                    }
                }
            }
        }
        return view('bibles.reader.books', compact('books', 'bible_id', 'language_id'));
    }

    /**
     * @param $bible_id
     * @param $book_id
     * @param $chapter
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function chapter($bible_id, $book_id, $chapter)
    {
        $fileset = BibleFileset::with('bible')
            ->where('id', $bible_id)
            ->where('asset_id', 'dbp-prod')
            ->where('set_type_code', 'text_plain')
            ->firstOrFail();

        $verses = BibleVerse::where('hash_id', $fileset->hash_id)->where('book_id', $book_id)
            ->where('chapter', $chapter)
            ->orderBy('verse_sequence')
            ->get();
        return view('bibles.reader.verses', compact('verses'));
    }
}
