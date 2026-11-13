<?php

namespace App\Transformers;

class PlanDayPlaylistItemsTransformer extends PlanTransformerBase
{
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform($plan)
    {
        // Only for show() with include_user_bible=true; omitted (not null) otherwise.
        $user_bible_field = !empty($this->params['include_user_bible'])
            ? ['user_bible' => $this->params['user_bible'] ?? null]
            : [];

        // Only for show() with localized=true (PBI 104292): the plans_localization row for the app locale, or null when
        // the plan has none in that language. Off, nothing changes: no row, so name and thumbnail are the plan's own,
        // and the two keys are omitted (not null). On, both keys are always present, null when there is no value.
        $localized = !empty($this->params['localized']);
        $localization = $localized ? ($this->params['localization'] ?? null) : null;
        $localized_fields = $localized
            ? [
                'description' => $localization->description ?? null,
                'localization' => $localization === null ? null : [
                    'id' => $localization->id,
                    'iso' => $localization->iso,
                    'alt_locale' => $localization->alt_locale,
                    'bible_id' => $localization->bible_id,
                ],
            ]
            : [];

        return array_merge([
            "id" => $plan->id,
            'name' => $localization->name ?? $plan->name,
            'thumbnail' => $localization->thumbnail ?? $plan->thumbnail,
            ...$localized_fields,
            "featured" => $plan->featured,
            "suggested_start_date" => $plan->suggested_start_date,
            "draft" => $plan->draft,
            "created_at" => $plan->created_at,
            "updated_at" => $plan->updated_at,
            "start_date" => $plan->start_date,
            "percentage_completed" => $plan->percentage_completed,
        ], $user_bible_field, [
            "days" => $plan->days->map(function ($day) {
                $day_result = [
                    "id" => $day->id,
                    "playlist_id" => $day->playlist_id,
                    "completed" => $day->completed
                ];

                // Only for show() with include_completed_at=true; omitted (not null) otherwise. It follows `completed`,
                // so a day that is not completed never carries a date.
                if (!empty($this->params['include_completed_at'])) {
                    $day_result['completed_at'] = $day->completed
                        ? ($this->params['completed_at_by_day'][$day->id] ?? null)
                        : null;
                }

                if ($this->params['show_details']) {
                    $day_result["playlist"] = $this->parsePlaylistData($day->playlist);
                } else {
                    $day_result["playlist_is_empty"] = $day->playlist_is_empty;
                }

                return $day_result;
            }),
            "total_users" => $plan->total_users,
            "user" => [
                "id" => $plan->user->id,
                "name" => $plan->user->name
            ]
        ]);
    }
}
