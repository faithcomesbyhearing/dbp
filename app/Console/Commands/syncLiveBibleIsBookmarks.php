<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User\User;
use App\Models\Bible\Book;
use App\Models\Bible\Bible;
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
        $from_date = $this->argument('date');
        $from_date = Carbon::createFromFormat('Y-m-d', $from_date)->startOfDay();

        $bibles = Bible::get();
        $bible_ids = $bibles->pluck('id')->toArray();

        echo "\n" . Carbon::now() . ': liveBibleis to v4 bookmarks sync started.';
        $chunk_size = config('settings.liveBibleisV4SyncChunkSize');
        $syncFile = config('settings.bibleSyncFilePath');
        $transition_bibles = convertCsvToArrayMap($syncFile);
        
        DB::connection('livebibleis_users')
            ->table('user_bookmarks')
            ->where('created_at', '>=', $from_date)
            ->orderBy('id')
            ->chunk($chunk_size, function ($bookmarks) use ($bible_ids, $transition_bibles, $chunk_size) {
                $bookmarks = $bookmarks->map(function ($bookmark) use ($transition_bibles) {
                    $user_email = DB::connection('livebibleis_users')->table('users')->where('id', $bookmark->user_id)->pluck('email')->first();
                    $v4_user_id = User::where(DB::raw('upper(email)'), '=', strtoupper($user_email))->pluck('id')->first();
                    if ($user_email && $v4_user_id) {
                        $bible_id = $bookmark->bible_id;
                        $v4_bible_id = array_key_exists($bible_id, $transition_bibles) ? $transition_bibles[$bible_id] : $bible_id;
                        return [
                            'user_id'     => $v4_user_id,
                            'bible_id'    => $v4_bible_id,
                            'book_id'     => $bookmark->book_id,
                            'chapter'     => $bookmark->chapter,
                            'verse_start' => $bookmark->verse_start,
                        ];
                    }
                });

                $user_ids = $bookmarks->pluck('user_id')->toArray();
                $v4_users = User::whereIn('id', $user_ids)->pluck('id')->toArray();
                $bookmarks = $bookmarks->filter(function ($bookmark) use ($bible_ids, $v4_users) {
                  if (!$bookmark) {
                      return false;
                  }
                  $bookmark_exists = Bookmark::where([
                      'user_id'           => $bookmark['user_id'], 
                      'bible_id'          => $bookmark['bible_id'],
                      'book_id'           => $bookmark['book_id'],
                      'chapter'           => $bookmark['chapter'],
                      'verse_start'       => $bookmark['verse_start'],
                  ])->first();
                  return validateLiveBibleIsAnnotation($bookmark, $v4_users, $bible_ids, $bookmark_exists);
                });

                $chunks = $bookmarks->chunk($chunk_size);
                foreach ($chunks as $chunk) {
                    Bookmark::insert($chunk->toArray());
                }

                echo "\n" . Carbon::now() . ': Inserted ' . sizeof($bookmarks) . ' new live bibleis bookmarks.';
            });
        echo "\n" . Carbon::now() . ": live bibleis to v4 bookmarks sync finalized.\n";
    }
}
