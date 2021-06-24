<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible\Book;
use App\Models\User\Study\Note;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class syncLiveBibleIsUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncLiveBibleIs:users {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the users with the live bibleis Database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $from_date = $this->argument('date');
        $from_date = Carbon::createFromFormat('Y-m-d', $from_date)->startOfDay();

        echo "\n" . Carbon::now() . ': liveBibleis to v4 users sync started.';
        $chunk_size = config('settings.liveBibleisV4SyncChunkSize');

        DB::connection('livebibleis_users')
            ->table('users')
            ->where('created_at', '>=', $from_date)
            ->orderBy('id')->chunk($chunk_size, function ($users) use ($chunk_size){
                $users = $users->map(function ($user) {
                    return [
                        'v2_id'       => $user->v2_id,
                        'name'        => $user->name,
                        'first_name'  => $user->first_name,
                        'last_name'   => $user->last_name,
                        'email'       => $user->email,
                        'password'    => $user->password,
                        'activated'   => $user->activated,
                        'token'       => $user->token,
                        'notes'       => 'inserted by syncLiveBibleIsUsers',
                    ];
                });
                
                $users = $users->filter(function ($user) {
                    $user_exists = User::where('email', $user['email'])->first();
                    return !$user_exists;
                });

                $chunks = $users->chunk($chunk_size);
                foreach ($chunks as $chunk) {
                    User::insert($chunk->toArray());
                }

                echo "\n" . Carbon::now() . ': Inserted ' . sizeof($users) . ' new liveBibleis users.';
            });
        echo "\n" . Carbon::now() . ": liveBibleis to v4 users sync finalized.\n";
    }
}
