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
        $this->dam_ids = [];
        $books = Book::select('id_osis', 'id')->get()->pluck('id', 'id_osis')->toArray();

        echo "\n" . Carbon::now() . ': liveBibleis to v4 bookmarks sync started.';
        $chunk_size = config('settings.v2V4SyncChunkSize');
        DB::connection($db_name)
            ->table('user_bookmarks')
            ->where('status', 'current')
            ->where('created', '>=', $from_date)
            ->orderBy('id')->chunk($chunk_size, function ($bookmarks) use ($filesets, $books) {
                $user_ids = $bookmarks->pluck('user_id')->toArray();
                $bookmark_ids = $bookmarks->pluck('id')->toArray();

                $v4_users = User::whereIn('id', $user_ids)->pluck('id');
                $v4_bookmarks = Bookmark::whereIn('id', $bookmark_ids)->pluck('id');

                $dam_ids = $bookmarks->pluck('dam_id')->reduce(function ($carry, $item) use ($filesets) {
                    if (!isset($carry[$item])) {
                        if (isset($this->dam_ids[$item])) {
                            $carry[$item] = $this->dam_ids[$item];
                            return $carry;
                        }
                        $fileset = getFilesetFromDamId($item, true, $filesets);
                        if ($fileset) {
                            $carry[$item] = $fileset;
                            $this->dam_ids[$item] = $fileset;
                        }
                    }
                    return $carry;
                }, []);

                $bookmarks = $bookmarks->filter(function ($bookmark) use ($dam_ids, $books, $v4_users, $v4_bookmarks) {
                    return validateV2Annotation($bookmark, $dam_ids, $books, $v4_users, $v4_bookmarks, false);
                });

                $bookmarks = $bookmarks->map(function ($bookmark) use ($v4_users, $books, $dam_ids) {
                    return [
                        'user_id'     => $v4_users[$bookmark->user_id],
                        'bible_id'    => $dam_ids[$bookmark->dam_id]->bible->first()->id,
                        'book_id'     => $books[$bookmark->book_id],
                        'chapter'     => $bookmark->chapter_id,
                        'verse_start' => $bookmark->verse_id,
                        'created_at'  => Carbon::createFromTimeString($bookmark->created),
                        'updated_at'  => Carbon::createFromTimeString($bookmark->updated),
                    ];
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
