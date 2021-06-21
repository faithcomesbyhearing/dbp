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

        $this->initColors();
        $filesets = BibleFileset::with('bible')->get();
        $this->dam_ids = [];
        $books = Book::select('id_osis', 'id')->get()->pluck('id', 'id_osis')->toArray();

        echo "\n" . Carbon::now() . ': liveBibleis to v4 highlights sync started.';
        $chunk_size = config('settings.v2V4SyncChunkSize');
        DB::connection($db_name)
            ->table('user_highlights')
            ->where('created', '>=', $from_date)
            ->orderBy('id')
            ->chunk($chunk_size, function ($highlights) use ($filesets, $books) {
                // live bible is user and highlights
                $user_ids = $highlights->pluck('user_id')->toArray();
                $highlight_ids = $highlights->pluck('id')->toArray();
                // v4 data
                $v4_users = User::whereIn('id', $user_ids)->pluck('id');
                $v4_highlights = Highlight::whereIn('id', $highlight_ids)->pluck('id');

                $dam_ids = $highlights->pluck('dam_id')->reduce(function ($carry, $item) use ($filesets) {
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

                $highlights = $highlights->filter(function ($highlight) use ($dam_ids, $books, $v4_users, $v4_highlights) {
                    return validateV2Annotation($highlight, $dam_ids, $books, $v4_users, $v4_highlights, false);
                });

                $highlights = $highlights->map(function ($highlight) use ($v4_users, $books, $dam_ids) {
                    return [
                        'user_id'           => $v4_users[$highlight->user_id],
                        'bible_id'          => $dam_ids[$highlight->dam_id]->bible->first()->id,
                        'book_id'           => $books[$highlight->book_id],
                        'chapter'           => $highlight->chapter_id,
                        'verse_start'       => $highlight->verse_id,
                        'verse_end'         => $highlight->verse_id,
                        'highlight_start'   => $highlight->highlight_start ?? 1,
                        'highlighted_words' => $highlight->highlighted_words ?? null,
                        'highlighted_color' => $this->getRelatedColorIdForHighlightColorString($highlight->color),
                        'created_at'        => Carbon::createFromTimeString($highlight->created),
                        'updated_at'        => Carbon::createFromTimeString($highlight->updated),
                    ];
                });

                $chunks = $highlights->chunk(5000);

                foreach ($chunks as $chunk) {
                    Highlight::insert($chunk->toArray());
                }

                echo "\n" . Carbon::now() . ': Inserted ' . sizeof($highlights) . ' new live bibleis highlights.';
            });
        echo "\n" . Carbon::now() . ": live bibleis to v4 highlights sync finalized.\n";
    }

    private function initColors()
    {
        $this->highlightColors = HighlightColor::select('color', 'id')->get()->pluck('id', 'color')->toArray();
    }

    private function getRelatedColorIdForHighlightColorString($v2_color)
    {
        $v4_colors_map = ['orange' => 'purple', 'green' => 'green', 'blue' => 'blue', 'pink' => 'pink', 'yellow' => 'yellow'];
        $v4_color = $v4_colors_map[$v2_color];

        if (isset($this->highlightColors[$v4_color])) {
            return $this->highlightColors[$v4_color];
        }

        $green = ['color' => 'green', 'hex' => 'addd79', 'red' => 173, 'green' => 221, 'blue' => 121, 'opacity' => 0.7];
        $blue = ['color' => 'blue', 'hex' => '87adcc', 'red' => 135, 'green' => 173, 'blue' => 204, 'opacity' => 0.7];
        $pink = ['color' => 'pink', 'hex' => 'ea9dcf', 'red' => 234, 'green' => 157, 'blue' => 207, 'opacity' => 0.7];
        $yellow = ['color' => 'yellow', 'hex' => 'e9de7f', 'red' => 223, 'green' => 222, 'blue' => 127, 'opacity' => 0.7];
        $purple = ['color' => 'purple', 'hex' => '8967ac', 'red' => 137, 'green' => 103, 'blue' => 172, 'opacity' => 0.7];
        $v4_colors = ['purple' => $purple, 'green' => $green, 'blue' => $blue, 'pink' => $pink, 'yellow' => $yellow];
        $highlightColor = HighlightColor::create($v4_colors[$v4_color]);
        $this->initColors();

        return $highlightColor->id;
    }
}
