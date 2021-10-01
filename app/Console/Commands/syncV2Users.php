<?php

namespace App\Console\Commands;

use App\Models\User\Role;
use Illuminate\Console\Command;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class syncV2Users extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncV2:users {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the Users with the V2 Database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $from_date = $this->argument('date');
        if ($from_date) {
            $from_date = Carbon::createFromFormat('Y-m-d', $from_date)->startOfDay();
        } else {
            $last_user_synced = User::whereNotNull('v2_id')->where('v2_id', '!=', 0)->orderBy('id', 'desc')->first();
            $from_date = $last_user_synced->created_at ?? Carbon::now()->startOfDay();
        }

        echo "\n" . Carbon::now() . ': v2 to v4 users sync started.';
        $chunk_size = config('settings.v2V4SyncChunkSize');
        DB::connection('dbp_users_v2')->table('user')->where('created', '>', $from_date)->orderBy('id')
            ->chunk($chunk_size, function ($users) {
                $v2_ids = $users->pluck('id');
                $v2_emails = $users->pluck('email');
                $v2_synced_users = User::whereIn('v2_id', $v2_ids)->orWhereIn('email', $v2_emails)->get();
                $v2_ids = $v2_synced_users->pluck('v2_id', 'v2_id');
                $v2_emails = $v2_synced_users->pluck('email', 'email')->toArray();
                $v2_emails = array_change_key_case($v2_emails, CASE_LOWER);

                $users = $users->filter(function ($user) use ($v2_emails, $v2_ids) {
                    return !isset($v2_emails[Str::lower($user->email)]) &&
                        !isset($v2_ids[$user->id]);
                });


                $users = $users->map(function ($user) {
                    return [
                        'v2_id'            => $user->id,
                        'name'             => $user->username ?? $user->email,
                        'password'         => bcrypt($user->password),
                        'first_name'       => $user->first_name,
                        'last_name'        => $user->last_name,
                        'token'            => Str::random(24),
                        'email'            => $user->email,
                        'activated'        => (int) $user->confirmed,
                        'created_at'       =>  Carbon::createFromTimeString($user->created),
                        'updated_at'       =>  Carbon::createFromTimeString($user->updated),
                    ];
                });

                $chunks = $users->chunk(5000);

                foreach ($chunks as $chunk) {
                    User::insert($chunk->toArray());
                }

                echo "\n" . Carbon::now() . ': Inserted ' . sizeof($users) . ' new v2 users.';
            });
        echo "\n" . Carbon::now() . ": v2 to v4 users sync finalized.\n";

        $default_project_id = config('settings.defaultProjectId');
        echo "\n" . Carbon::now() . ": Assign v2 users to default project started.\n";
        $user_role = Role::where('slug', 'user')->first();
        if (!$user_role) {
            echo "\n" . Carbon::now() . ": The Roles table has not been populated.\n";
        } else {
            DB::connection('dbp_users')
                ->statement(
                    'INSERT INTO project_members (project_id, user_id, role_id, created_at)
                    SELECT :default_project_id AS project_id,
                        id AS user_id,
                        :user_role_id AS role_id,
                        u.created_at
                    FROM users AS u
                    WHERE u.created_at >= :created_at
                    AND u.v2_id > 0
                    AND u.id NOT IN (
                        SELECT `project_members`.`user_id` FROM `dbp_users`.`project_members`
                    )',
                    [
                        'default_project_id' => $default_project_id,
                        'user_role_id' => $user_role->id,
                        'created_at' => $from_date,
                    ]
                );

            echo "\n" . Carbon::now() . ": Assign v2 users to default project finalized.\n";
        }
    }
}
