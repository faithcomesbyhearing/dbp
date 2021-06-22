<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User\User;
use App\Models\Bible\Book;
use App\Models\Bible\BibleFileset;
use App\Models\User\Study\Bookmark;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class syncLiveBibleIsBookmarks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncLiveBibleIs:bookmarks {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the Bookmarks with the live bibleis Database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $db_name = config('database.connections.livebibleis_users.database');

        $from_date = $this->argument('date');
        $from_date = Carbon::createFromFormat('Y-m-d', $from_date)->startOfDay();

        $filesets = BibleFileset::with('bible')->get();
        $this->bible_ids = [];

        echo "\n" . Carbon::now() . ': liveBibleis to v4 bookmarks sync started.';
        $chunk_size = config('settings.v2V4SyncChunkSize');
        $syncFile = config('settings.bibleSyncFilePath');
        $transition_bibles = convertCsvToArrayMap($syncFile);

        DB::connection($db_name)
            ->table('user_bookmarks')
            ->where('created_at', '>=', $from_date)
            ->orderBy('id')->chunk($chunk_size, function ($bookmarks) use ($filesets, $transition_bibles) {
                $bible_ids = $bookmarks->pluck('bible_id')->reduce(function ($carry, $item) use ($filesets) {
                    // checks if filesets actually exist for the given bible
                    $fileset = getFilesetFromDamId($item, true, $filesets);
                    if ($fileset) {
                        $carry[$item] = $fileset;
                        $this->bible_ids[$item] = $fileset;
                    }
                    return $carry;
                }, []);  

                $bookmarks = $bookmarks->map(function ($bookmark) use ($bible_ids, $transition_bibles) {
                    $bible_id = $bookmark->bible_id;
                    $v4_bible_id = array_key_exists($bible_id, $transition_bibles) ? $transition_bibles[$bible_id] : $bible_id;
                    return [
                        'user_id'     => $bookmark->user_id,
                        'bible_id'    => $bible_ids[$v4_bible_id]->bible->first()->id,
                        'book_id'     => $bookmark->book_id,
                        'chapter'     => $bookmark->chapter,
                        'verse_start' => $bookmark->verse_start,
                        'created_at'  => Carbon::createFromTimeString($bookmark->created_at),
                        'updated_at'  => Carbon::createFromTimeString($bookmark->updated_at),
                    ];
                });
                
                $bookmarks = $bookmarks->filter(function ($bookmark) use ($bible_ids) {
                  $bookmark_exists = Bookmark::where([
                      'user_id'           => $bookmark['user_id'], 
                      'bible_id'          => $bookmark['bible_id'],
                      'book_id'           => $bookmark['book_id'],
                      'chapter'           => $bookmark['chapter'],
                      'verse_start'       => $bookmark['verse_start'],
                  ])->first();

                  return validateLiveBibleIsAnnotation($bookmark, $bible_ids, $bookmark_exists);
                });

                $chunks = $bookmarks->chunk(5000);
                foreach ($chunks as $chunk) {
                    Bookmark::insert($chunk->toArray());
                }

                echo "\n" . Carbon::now() . ': Inserted ' . sizeof($bookmarks) . ' new live bibleis bookmarks.';
            });
        echo "\n" . Carbon::now() . ": live bibleis to v4 bookmarks sync finalized.\n";
    }
}
