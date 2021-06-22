<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User\Study\HighlightColor;
use App\Models\Bible\Bible;
use App\Models\Bible\Book;
use App\Models\User\User;
use App\Models\User\Study\Highlight;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class syncLiveBibleIsHighlights extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncLiveBibleIs:highlights {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the Highlights with the live bibleis Database';

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

        echo "\n" . Carbon::now() . ': liveBibleis to v4 highlights sync started.';
        $chunk_size = config('settings.v2V4SyncChunkSize');
        $syncFile = config('settings.bibleSyncFilePath');
        $transition_bibles = convertCsvToArrayMap($syncFile);

        DB::connection('livebibleis_users')
            ->table('user_highlights')
            ->where('created_at', '>=', $from_date)
            ->orderBy('id')
            ->chunk($chunk_size, function ($highlights) use ($bible_ids, $transition_bibles) {
                $highlights = $highlights->map(function ($highlight) use ($transition_bibles) {
                    $bible_id = $highlight->bible_id;
                    $v4_bible_id = array_key_exists($bible_id, $transition_bibles) ? $transition_bibles[$bible_id] : $bible_id;
                    return [
                        'user_id'           => $highlight->user_id,
                        'bible_id'          => $v4_bible_id,
                        'book_id'           => $highlight->book_id,
                        'chapter'           => $highlight->chapter,
                        'verse_start'       => $highlight->verse_start,
                        'verse_end'         => $highlight->verse_start,
                        'highlight_start'   => $highlight->highlight_start ?? 1,
                        'highlighted_words' => $highlight->highlighted_words ?? null,
                        'highlighted_color' => $highlight->highlighted_color,
                        'created_at'        => Carbon::createFromTimeString($highlight->created_at),
                        'updated_at'        => Carbon::createFromTimeString($highlight->updated_at),
                    ];
                });

                $user_ids = $highlights->pluck('user_id')->toArray();
                $v4_users = User::whereIn('id', $user_ids)->pluck('id')->toArray();
                $highlights = $highlights->filter(function ($highlight) use ($bible_ids, $v4_users) {
                    $highlight_exists = Highlight::where([
                        'user_id'           => $highlight['user_id'], 
                        'bible_id'          => $highlight['bible_id'],
                        'book_id'           => $highlight['book_id'],
                        'chapter'           => $highlight['chapter'],
                        'verse_start'       => $highlight['verse_start'],
                    ])->first();
                    return validateLiveBibleIsAnnotation($highlight, $v4_users, $bible_ids, $highlight_exists);
                });

                $chunks = $highlights->chunk(5000);
                foreach ($chunks as $chunk) {
                    Highlight::insert($chunk->toArray());
                }

                echo "\n" . Carbon::now() . ': Inserted ' . sizeof($highlights) . ' new live bibleis highlights.';
            });
        echo "\n" . Carbon::now() . ": live bibleis to v4 highlights sync finalized.\n";
    }
}
