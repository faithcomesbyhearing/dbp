<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanUpPlanDaysCompletedTable extends Command
{
    protected $signature = 'userPlanDays:cleanup';
    protected $description = 'Cleans up the user plans';

    public function handle()
    {
        $this->info('DB:Table (plan_days_completed) cleanup has started ...');

        $db = DB::connection('dbp_users');

        $db->transaction(function () use ($db) {
            $db->statement('DROP TEMPORARY TABLE IF EXISTS plan_days_completed_temp');
            $db->statement('CREATE TEMPORARY TABLE plan_days_completed_temp AS
                    SELECT pdc.user_id, pdc.plan_day_id, COUNT(pdc.user_id)
                    FROM plan_days_completed pdc
                    GROUP BY pdc.user_id, pdc.plan_day_id HAVING COUNT(pdc.user_id) > 1'
            );
            $db->statement('DELETE plan_days_completed FROM plan_days_completed INNER JOIN plan_days_completed_temp
                ON plan_days_completed_temp.user_id = plan_days_completed.user_id
                AND plan_days_completed_temp.plan_day_id = plan_days_completed.plan_day_id'
            );
            $db->statement('INSERT INTO plan_days_completed (user_id, plan_day_id)
                SELECT user_id, plan_day_id FROM plan_days_completed_temp'
            );
            $db->statement('DROP TEMPORARY TABLE IF EXISTS plan_days_completed_temp');
        });

        $this->info('Trying to create (plan_days_completed_user_id_plan_day_id_unique) constraint ...');

        if (!constraintExists(
            $db,
            'plan_days_completed',
            'plan_days_completed_user_id_plan_day_id_unique'
        )) {
            $db->statement('ALTER TABLE plan_days_completed
                ADD CONSTRAINT plan_days_completed_user_id_plan_day_id_unique UNIQUE (user_id, plan_day_id)'
            );
        }

        $this->info('DB:Table (plan_days_completed) cleanup complete.');
    }
}
