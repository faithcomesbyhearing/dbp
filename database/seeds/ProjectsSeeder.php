<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

use Faker\Generator as Faker;
use App\Models\User\Project;
use App\Models\User\ProjectOauthProvider;

class ProjectsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @param Faker $faker
     *
     * @return void
     */
    public function run(Faker $faker)
    {
        $dbp_users = config('database.connections.dbp_users.database');
        Project::create([
            'id'              => unique_random($dbp_users.'.projects', 'id'),
            'name'            => 'Digital Bible Platform',
            'url_avatar'      => $faker->url,
            'url_avatar_icon' => $faker->url,
            'url_site'        => $faker->url,
            'description'     => $faker->paragraph(3, true),
            'sensitive'       => false
        ]);

        $project_count = random_int(3, 5);
        while ($project_count > 0) {
            Project::create([
                'id'              => unique_random($dbp_users.'.projects', 'id'),
                'name'            => $faker->company,
                'url_avatar'      => $faker->url,
                'url_avatar_icon' => $faker->url,
                'url_site'        => $faker->url,
                'description'     => $faker->paragraph(3, true),
                'sensitive'       => false
            ]);

            ProjectOauthProvider::create([
                'id'            => unique_random($dbp_users.'.project_oauth_providers', 'id'),
                'name'          => 'facebook',
                'project_id'    => $project_count,
                'client_id'     => unique_random($dbp_users.'.project_oauth_providers', 'client_id'),
                'client_secret' => unique_random($dbp_users.'.project_oauth_providers', 'client_secret'),
                'callback_url'  => 'https://dbp4.org/login/callback/facebook',
                'description'   => (string) $faker->paragraph(),
            ]);

            ProjectOauthProvider::create([
                'id'            => unique_random($dbp_users.'.project_oauth_providers', 'id'),
                'name'          => 'google',
                'project_id'    => $project_count,
                'client_id'     => unique_random($dbp_users.'.project_oauth_providers', 'client_id'),
                'client_secret' => unique_random($dbp_users.'.project_oauth_providers', 'client_secret'),
                'callback_url'  => 'https://dbp4.org/login/callback/google',
                'description'   => (string) $faker->paragraph(),
            ]);

            $project_count--;
        }
    }
}
