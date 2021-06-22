<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User\Study\HighlightColor;
use App\Models\Bible\BibleFileset;
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
        $db_name = config('database.connections.livebibleis_users.database');

        $from_date = $this->argument('date');
        $from_date = Carbon::createFromFormat('Y-m-d', $from_date)->startOfDay();

        $filesets = BibleFileset::with('bible')->get();
        $this->bible_ids = [];

        echo "\n" . Carbon::now() . ': liveBibleis to v4 highlights sync started.';
        $chunk_size = config('settings.v2V4SyncChunkSize');
        $syncFile = config('settings.bibleSyncFilePath');
        $transition_bibles = convertCsvToArrayMap($syncFile);

        DB::connection('dbp_users')
            ->table('user_highlights')
            ->where('created_at', '>=', $from_date)
            ->orderBy('id')
            ->chunk($chunk_size, function ($highlights) use ($filesets, $transition_bibles) {
                $bible_ids = $highlights->pluck('bible_id')->reduce(function ($carry, $item) use ($filesets) {
                    // checks if filesets actually exist for the given bible
                    $fileset = getFilesetFromDamId($item, true, $filesets);
                    if ($fileset) {
                        $carry[$item] = $fileset;
                        $this->bible_ids[$item] = $fileset;
                    }
                    return $carry;
                }, []);

                $highlights = $highlights->map(function ($highlight) use ($bible_ids, $transition_bibles) {
                    $bible_id = $highlight->bible_id;
                    $v4_bible_id = array_key_exists($bible_id, $transition_bibles) ? $transition_bibles[$bible_id] : $bible_id;
                    return [
                        'user_id'           => $highlight->user_id,
                        'bible_id'          => $bible_ids[$v4_bible_id]->bible->first()->id,
                        'book_id'           => $highlight->book_id,
                        'chapter'           => $highlight->chapter,
                        'verse_start'       => $highlight->verse_start,
                        'verse_end'         => $highlight->verse_end,
                        'highlight_start'   => $highlight->highlight_start ?? 1,
                        'highlighted_words' => $highlight->highlighted_words ?? null,
                        'highlighted_color' => $highlight->highlighted_color,
                        'created_at'        => Carbon::createFromTimeString($highlight->created_at),
                        'updated_at'        => Carbon::createFromTimeString($highlight->updated_at),
                    ];
                });

                $highlights = $highlights->filter(function ($highlight) use ($bible_ids) {
                    $highlight_exists = Highlight::where([
                        'user_id'           => $highlight['user_id'], 
                        'bible_id'          => $highlight['bible_id'],
                        'book_id'           => $highlight['book_id'],
                        'chapter'           => $highlight['chapter'],
                        'verse_start'       => $highlight['verse_start'],
                        'verse_end'         => $highlight['verse_end'],
                    ])->first();
                    return validateLiveBibleIsAnnotation($highlight, $bible_ids, $highlight_exists);
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
