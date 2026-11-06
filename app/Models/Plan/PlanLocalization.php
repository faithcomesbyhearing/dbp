<?php

namespace App\Models\Plan;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Order a plan's rows in one app language so the row that applies to the app locale comes first: the exact
     * (iso, alt_locale) row, else the row with no alt_locale, else the lowest id in that language. Only the
     * ordering: the caller filters the plan and takes the first row (PBI 102556; Plan Details, PBI 104292, the same).
     *
     * Matching is case-insensitive through the columns' collation (utf8mb4_unicode_ci), so `FR-ca` finds `fr-CA`.
     * With no alt_locale, `<=> NULL` is true for the NULL row, which therefore already comes first.
     *
     * @param Builder $query
     * @param string $iso
     * @param string|null $alt_locale
     *
     * @return Builder
     */
    public function scopeForAppLocale(Builder $query, string $iso, ?string $alt_locale) : Builder
    {
        $query->where('plans_localization.iso', $iso)
            ->orderByRaw('plans_localization.alt_locale <=> ? DESC', [$alt_locale])
            ->orderByRaw('plans_localization.alt_locale IS NULL DESC')
            ->orderBy('plans_localization.id');

        return $query;
    }
}
