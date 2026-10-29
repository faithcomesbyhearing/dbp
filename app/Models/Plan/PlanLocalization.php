<?php

namespace App\Models\Plan;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * App\Models\Plan\PlanLocalization
 *
 * How a plan presents itself in one app locale: its name, thumbnail, description and recommended Bible. One row per
 * (plan, iso, alt_locale), with a NULL alt_locale counting as a value (unique key on dbp_users.plans_localization).
 * Written only through PlanLocalizationsController, by plan admins and plan editors.
 *
 * The response schema lives in PlanLocalizationTransformer: protected swagger properties here would shadow the
 * attributes inside the model.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $plan_id
 * @property string $iso
 * @property string|null $alt_locale
 * @property string $name
 * @property string|null $thumbnail
 * @property string|null $description
 * @property string|null $bible_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Plan|null $plan
 */
class PlanLocalization extends Model
{
    protected $connection = 'dbp_users';
    protected $table      = 'plans_localization';
    protected $fillable   = ['plan_id', 'iso', 'alt_locale', 'name', 'thumbnail', 'description', 'bible_id'];

    /**
     * The plan, or null when it is soft-deleted (Plan uses SoftDeletes).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
