<?php

namespace App\Transformers;

use App\Models\Plan\PlanLocalization;

/**
 * One plan localization, flat. Used only by the v4_internal_plans_localizations.* routes, which are v4-only (no v2
 * route reaches PlanLocalizationsController), so there is no version or route switch.
 *
 * @OA\Schema (
 *   type="object",
 *   schema="v4_plan_localization",
 *   title="Plan localization",
 *   description="How a featured plan presents itself in one app locale. Unique per plan, iso and alt_locale (a missing alt_locale counts as a value).",
 *   @OA\Property(property="id", type="integer", example=12, description="The localization id"),
 *   @OA\Property(property="plan_id", type="integer", example=209, description="The plan it belongs to"),
 *   @OA\Property(property="iso", type="string", minLength=3, maxLength=3, example="fra", description="The language's 3-letter code, as stored in languages (lower case)"),
 *   @OA\Property(property="alt_locale", type="string", maxLength=12, nullable=true, example="fr-CA", description="A locale with a region or script, in BCP 47 case (fr-CA, zh-Hant-HK, es-419); null for the language's default row. Its language agrees with iso."),
 *   @OA\Property(property="name", type="string", maxLength=191, example="La Bible en un an", description="The plan's name in this locale"),
 *   @OA\Property(property="thumbnail", type="string", maxLength=191, nullable=true, example=null, description="The image url for this locale"),
 *   @OA\Property(property="description", type="string", nullable=true, description="The plan's description in this locale (at most 65,535 bytes)"),
 *   @OA\Property(property="bible_id", type="string", maxLength=12, nullable=true, example="FRALSG", description="The recommended Bible id, in the Bible's own case"),
 *   @OA\Property(property="created_at", type="string", example="2026-10-22T15:04:05.000000Z"),
 *   @OA\Property(property="updated_at", type="string", example="2026-10-22T15:04:05.000000Z")
 * )
 */
class PlanLocalizationTransformer extends BaseTransformer
{
    /**
     * @param PlanLocalization $localization
     *
     * @return array
     */
    public function transform(PlanLocalization $localization)
    {
        return [
            'id'          => (int) $localization->id,
            'plan_id'     => (int) $localization->plan_id,
            'iso'         => $localization->iso,
            'alt_locale'  => $localization->alt_locale,
            'name'        => $localization->name,
            'thumbnail'   => $localization->thumbnail,
            'description' => $localization->description,
            'bible_id'    => $localization->bible_id,
            'created_at'  => $localization->created_at,
            'updated_at'  => $localization->updated_at,
        ];
    }
}
