<?php

namespace App\Http\Controllers\Plan;

use App\Http\Controllers\APIController;
use App\Models\Bible\Bible;
use App\Models\Language\Language;
use App\Models\Plan\Plan;
use App\Models\Plan\PlanLocalization;
use App\Traits\CheckProjectMembership;
use App\Traits\PlanEditorGuard;
use App\Transformers\PlanLocalizationTransformer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Fractalistic\ArraySerializer;

/**
 * Create, read, list, update and delete a featured plan's localizations (dbp_users.plans_localization): its name,
 * thumbnail, description and recommended Bible per app locale. API task 102557.
 *
 * Editor-only. Every action runs behind PlanEditorGuard, registered in the constructor for all actions: a valid key,
 * a user token (APIToken:check on the route), a project shared with the key's owner, and the plan_admin or
 * plan_editor role, checked before anything is looked up. Plans and rows are then loaded only through
 * findEditablePlan() and findEditableLocalization(), which answer 404 for an unknown id and 403 for a plan_editor on
 * a plan they did not create.
 *
 * Fields come from a JSON body only. A key that is present counts as sent, so an update changes only what was sent,
 * and null (or "", which ConvertEmptyStringsToNull turns into null) clears an optional field.
 */
class PlanLocalizationsController extends APIController
{
    use CheckProjectMembership;
    use PlanEditorGuard;

    /** The columns a client may set; plan_id comes from the URL or the row. */
    private const EDITABLE_FIELDS = ['iso', 'alt_locale', 'name', 'thumbnail', 'description', 'bible_id'];

    public function __construct()
    {
        // parent::__construct() first: it checks the key and sets $this->api, without which replyWithError() would
        // return a Blade view with status 200.
        parent::__construct();
        $this->guardPlanEditors();
    }

    /**
     * @OA\Get(
     *     path="/plans/{plan_id}/localizations",
     *     tags={"Plans"},
     *     summary="List a plan's localizations (plan admins and editors)",
     *     description="Every localization of the plan, ordered by id; an empty list when it has none. Needs a user token whose user holds the plan_admin role (any plan) or plan_editor (only plans the user created): otherwise 403, checked before the plan is looked up. Works on a plan that is no longer featured; a deleted plan is a 404.",
     *     operationId="v4_internal_plans_localizations.index",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/v4_plan_localization"))
     *         ))
     *     ),
     *     @OA\Response(response=401, description="No valid user token, or the user shares no project with the key's owner"),
     *     @OA\Response(response=403, description="The user is not a plan admin or plan editor, or is a plan editor and did not create this plan"),
     *     @OA\Response(response=404, description="Plan Not Found")
     * )
     *
     * @param Request $request
     * @param int $plan_id
     *
     * @return mixed
     */
    public function index(Request $request, $plan_id)
    {
        $plan = $this->findEditablePlan($request, $plan_id);
        if (!$plan instanceof Plan) {
            return $plan;
        }

        $localizations = PlanLocalization::where('plan_id', $plan->id)->orderBy('id')->get();

        return $this->setStatusCode(200)->reply(
            fractal($localizations, new PlanLocalizationTransformer(), $this->serializer)
        );
    }

    /**
     * @OA\Post(
     *     path="/plans/{plan_id}/localizations",
     *     tags={"Plans"},
     *     summary="Add a localization to a featured plan (plan admins and editors)",
     *     description="Adds the plan's name, thumbnail, description and recommended Bible for one language (iso) or locale (iso plus alt_locale). Send the fields as a JSON object with Content-Type: application/json. The plan must be featured (a draft is fine). A plan already having a row for the same iso and alt_locale (compared case-insensitively; no alt_locale counts as a value) gives 409 with that row's id. Needs plan_admin, or plan_editor on a plan the user created.",
     *     operationId="v4_internal_plans_localizations.store",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json",
     *          @OA\Schema(
     *              required={"iso","name"},
     *              @OA\Property(property="iso", ref="#/components/schemas/v4_plan_localization/properties/iso"),
     *              @OA\Property(property="alt_locale", ref="#/components/schemas/v4_plan_localization/properties/alt_locale"),
     *              @OA\Property(property="name", ref="#/components/schemas/v4_plan_localization/properties/name"),
     *              @OA\Property(property="thumbnail", ref="#/components/schemas/v4_plan_localization/properties/thumbnail"),
     *              @OA\Property(property="description", ref="#/components/schemas/v4_plan_localization/properties/description"),
     *              @OA\Property(property="bible_id", ref="#/components/schemas/v4_plan_localization/properties/bible_id"),
     *              @OA\Property(property="plan_id", type="integer", description="Optional; when sent it must equal the plan in the URL")
     *          )
     *     )),
     *     @OA\Response(response=200, description="The new localization", @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_plan_localization"))),
     *     @OA\Response(response=401, description="No valid user token, or the user shares no project with the key's owner"),
     *     @OA\Response(response=403, description="The user is not a plan admin or plan editor, or is a plan editor and did not create this plan"),
     *     @OA\Response(response=404, description="Plan Not Found"),
     *     @OA\Response(response=409, description="The plan already has a localization for this iso and alt_locale; nothing was written"),
     *     @OA\Response(response=422, description="The plan is not featured, or a field is missing or invalid (every problem in one message); nothing was written")
     * )
     *
     * @param Request $request
     * @param int $plan_id
     *
     * @return mixed
     */
    public function store(Request $request, $plan_id)
    {
        $plan = $this->findEditablePlan($request, $plan_id);
        if (!$plan instanceof Plan) {
            return $plan;
        }

        if (!$plan->featured) {
            return $this->notFeatured();
        }

        $body = $this->jsonBody($request);
        if (!is_array($body)) {
            return $body;
        }

        $normalized = self::normalizeFields($body, true, (int) $plan->id);
        $values = $normalized['values'];
        $errors = array_merge($normalized['errors'], $this->resolveLookups($values));

        // Checked here on create, where there is no stored row to lock; only when iso and alt_locale both passed.
        if (isset($values['iso'], $values['alt_locale'])) {
            $pair_error = $this->isoAltLocaleError($values['iso'], $values['alt_locale']);
            if ($pair_error !== null) {
                $errors[] = $pair_error;
            }
        }

        if (!empty($errors)) {
            return $this->setStatusCode(422)->replyWithError(implode(' ', $errors));
        }

        $iso = $values['iso'];
        $alt_locale = $values['alt_locale'] ?? null;

        // Every check that can fail ran above, outside the transaction, so it holds only the duplicate pre-check and
        // the insert. It must be on dbp_users (\DB::transaction() would open it on the content connection). The
        // insert's foreign-key check holds a shared lock on the plan row until commit; keeping this short keeps a
        // concurrent Save on the same plan (which locks that row) waiting only milliseconds.
        try {
            $outcome = DB::connection('dbp_users')->transaction(function () use ($plan, $values, $iso, $alt_locale) {
                $existing_id = $this->duplicateOf((int) $plan->id, $iso, $alt_locale);
                if ($existing_id !== null) {
                    return ['duplicate' => $existing_id];
                }

                $localization = new PlanLocalization(['plan_id' => $plan->id] + $values);
                $localization->save();

                return ['localization' => $localization];
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent create won between the pre-check and the insert (the unique key caught it). Looked up again
            // now that the transaction has rolled back: under REPEATABLE READ a read inside it could not see the
            // winner. Only MySQL's duplicate-key error lands here; any other database error is not caught.
            $outcome = ['duplicate' => $this->duplicateOf((int) $plan->id, $iso, $alt_locale)];
        }

        if (array_key_exists('duplicate', $outcome)) {
            return $this->setStatusCode(409)->replyWithError(
                self::duplicateMessage((int) $plan->id, $iso, $alt_locale, $outcome['duplicate'])
            );
        }

        $localization = $outcome['localization'];
        $this->logWrite('create', $request, $localization);

        return $this->replyWithLocalization($localization->fresh() ?? $localization);
    }

    /**
     * @OA\Get(
     *     path="/plans/localizations/{localization_id}",
     *     tags={"Plans"},
     *     summary="One plan localization (plan admins and editors)",
     *     description="Needs plan_admin, or plan_editor on a plan the user created. A localization whose plan was deleted is a 404.",
     *     operationId="v4_internal_plans_localizations.show",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="localization_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/v4_plan_localization/properties/id")),
     *     @OA\Response(response=200, description="successful operation", @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_plan_localization"))),
     *     @OA\Response(response=401, description="No valid user token, or the user shares no project with the key's owner"),
     *     @OA\Response(response=403, description="The user is not a plan admin or plan editor, or is a plan editor and did not create the plan"),
     *     @OA\Response(response=404, description="Plan Localization Not Found")
     * )
     *
     * @param Request $request
     * @param int $localization_id
     *
     * @return mixed
     */
    public function show(Request $request, $localization_id)
    {
        $localization = $this->findEditableLocalization($request, $localization_id);
        if (!$localization instanceof PlanLocalization) {
            return $localization;
        }

        return $this->replyWithLocalization($localization);
    }

    /**
     * @OA\Put(
     *     path="/plans/localizations/{localization_id}",
     *     tags={"Plans"},
     *     summary="Change a plan localization (plan admins and editors)",
     *     description="Changes only the fields sent, as a JSON object with Content-Type: application/json. null or an empty string clears alt_locale, thumbnail, description or bible_id; name and iso cannot be cleared. plan_id, id, created_at and updated_at may be sent back unchanged and are ignored, so the object read can be sent back; a different plan_id is a 422. The plan must still be featured. A change that would duplicate another row's iso and alt_locale gives 409. Needs plan_admin, or plan_editor on a plan the user created.",
     *     operationId="v4_internal_plans_localizations.update",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="localization_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/v4_plan_localization/properties/id")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json",
     *          @OA\Schema(
     *              @OA\Property(property="iso", ref="#/components/schemas/v4_plan_localization/properties/iso"),
     *              @OA\Property(property="alt_locale", ref="#/components/schemas/v4_plan_localization/properties/alt_locale"),
     *              @OA\Property(property="name", ref="#/components/schemas/v4_plan_localization/properties/name"),
     *              @OA\Property(property="thumbnail", ref="#/components/schemas/v4_plan_localization/properties/thumbnail"),
     *              @OA\Property(property="description", ref="#/components/schemas/v4_plan_localization/properties/description"),
     *              @OA\Property(property="bible_id", ref="#/components/schemas/v4_plan_localization/properties/bible_id")
     *          )
     *     )),
     *     @OA\Response(response=200, description="The localization as stored after the change", @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_plan_localization"))),
     *     @OA\Response(response=401, description="No valid user token, or the user shares no project with the key's owner"),
     *     @OA\Response(response=403, description="The user is not a plan admin or plan editor, or is a plan editor and did not create the plan"),
     *     @OA\Response(response=404, description="Plan Localization Not Found"),
     *     @OA\Response(response=409, description="Another localization of the plan already has this iso and alt_locale; nothing was written"),
     *     @OA\Response(response=422, description="The plan is not featured, no field was sent, or a field is invalid; nothing was written")
     * )
     *
     * @param Request $request
     * @param int $localization_id
     *
     * @return mixed
     */
    public function update(Request $request, $localization_id)
    {
        $localization = $this->findEditableLocalization($request, $localization_id);
        if (!$localization instanceof PlanLocalization) {
            return $localization;
        }

        $plan = $localization->plan;
        if (!$plan->featured) {
            return $this->notFeatured();
        }

        $body = $this->jsonBody($request);
        if (!is_array($body)) {
            return $body;
        }

        $normalized = self::normalizeFields($body, false, (int) $plan->id);
        $values = $normalized['values'];
        $errors = array_merge($normalized['errors'], $this->resolveLookups($values));

        if (!empty($errors)) {
            return $this->setStatusCode(422)->replyWithError(implode(' ', $errors));
        }

        // The pair the row will end up with, set inside the transaction and read again by the 409 path below.
        $iso = null;
        $alt_locale = null;

        // The row is re-read under a lock, so the iso/alt_locale pair is checked against what it will end up with: a
        // concurrent PUT changing the other field waits here instead of leaving a mismatched pair. The two
        // content-DB lookups that check needs are indexed reads of languages on another connection.
        try {
            $outcome = DB::connection('dbp_users')->transaction(
                function () use ($localization, $values, &$iso, &$alt_locale) {
                    $row = PlanLocalization::whereKey($localization->id)->lockForUpdate()->first();
                    if (!$row) {
                        return ['missing' => true];
                    }

                    $iso = $values['iso'] ?? $row->iso;
                    $alt_locale = array_key_exists('alt_locale', $values) ? $values['alt_locale'] : $row->alt_locale;

                    if (array_key_exists('iso', $values) || array_key_exists('alt_locale', $values)) {
                        $pair_error = $this->isoAltLocaleError($iso, $alt_locale);
                        if ($pair_error !== null) {
                            return ['invalid' => $pair_error];
                        }

                        if ($iso !== $row->iso || $alt_locale !== $row->alt_locale) {
                            $existing_id = $this->duplicateOf((int) $row->plan_id, $iso, $alt_locale, (int) $row->id);
                            if ($existing_id !== null) {
                                return ['duplicate' => $existing_id];
                            }
                        }
                    }

                    $row->fill($values);
                    $row->save();

                    return ['localization' => $row];
                },
                3
            );
        } catch (UniqueConstraintViolationException $e) {
            // As in store(): a concurrent write took the pair; looked up after the rollback.
            $outcome = [
                'duplicate' => $this->duplicateOf((int) $plan->id, (string) $iso, $alt_locale, (int) $localization->id)
            ];
        }

        if (isset($outcome['missing'])) {
            return $this->setStatusCode(404)->replyWithError('Plan Localization Not Found');
        }

        if (isset($outcome['invalid'])) {
            return $this->setStatusCode(422)->replyWithError($outcome['invalid']);
        }

        if (array_key_exists('duplicate', $outcome)) {
            return $this->setStatusCode(409)->replyWithError(
                self::duplicateMessage((int) $plan->id, (string) $iso, $alt_locale, $outcome['duplicate'])
            );
        }

        $updated = $outcome['localization'];
        $this->logWrite('update', $request, $updated);

        return $this->replyWithLocalization($updated->fresh() ?? $updated);
    }

    /**
     * @OA\Delete(
     *     path="/plans/localizations/{localization_id}",
     *     tags={"Plans"},
     *     summary="Remove a plan localization (plan admins and editors)",
     *     description="Deletes the localization; the plan and its other localizations are untouched. Works on a plan that is no longer featured. Needs plan_admin, or plan_editor on a plan the user created.",
     *     operationId="v4_internal_plans_localizations.destroy",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="localization_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/v4_plan_localization/properties/id")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(
     *             type="object",
     *             @OA\Property(property="id", ref="#/components/schemas/v4_plan_localization/properties/id"),
     *             @OA\Property(property="message", type="string", example="Plan localization deleted")
     *         ))
     *     ),
     *     @OA\Response(response=401, description="No valid user token, or the user shares no project with the key's owner"),
     *     @OA\Response(response=403, description="The user is not a plan admin or plan editor, or is a plan editor and did not create the plan"),
     *     @OA\Response(response=404, description="Plan Localization Not Found")
     * )
     *
     * @param Request $request
     * @param int $localization_id
     *
     * @return mixed
     */
    public function destroy(Request $request, $localization_id)
    {
        $localization = $this->findEditableLocalization($request, $localization_id);
        if (!$localization instanceof PlanLocalization) {
            return $localization;
        }

        $deleted = DB::connection('dbp_users')->transaction(function () use ($localization) {
            $row = PlanLocalization::whereKey($localization->id)->lockForUpdate()->first();
            if (!$row) {
                return false;
            }
            $row->delete();

            return true;
        }, 3);

        if (!$deleted) {
            return $this->setStatusCode(404)->replyWithError('Plan Localization Not Found');
        }

        $this->logWrite('delete', $request, $localization);

        return $this->setStatusCode(200)->reply([
            'id'      => (int) $localization->id,
            'message' => 'Plan localization deleted',
        ]);
    }

    /**
     * The DB-free part of the body check for create and update, kept static so it can be unit tested.
     *
     * A field counts as sent when its key is present, which is how "not sent" (kept) and null (cleared) are told
     * apart. In a request, TrimStrings and ConvertEmptyStringsToNull have already trimmed the values and turned blank
     * ones into null; a blank string reaching here directly counts as null the same way. Values are not trimmed here,
     * so "fra\n" is rejected rather than accepted (every pattern ends in \z, not $, which allows a trailing newline).
     * Lengths are checked here because the users connection is not strict: MySQL would truncate silently.
     *
     * @param array $body the decoded JSON object
     * @param bool $creating true for create (iso and name required), false for update (at least one field)
     * @param int $plan_id the plan in the URL (create) or the row's plan (update); a sent plan_id must equal it
     *
     * @return array ['values' => the sent editable fields, cleaned (iso lower case, alt_locale in canonical case,
     *               null for a clear), 'errors' => the 422 messages, in field order]
     */
    public static function normalizeFields(array $body, bool $creating, int $plan_id) : array
    {
        $sent = [];
        foreach (self::EDITABLE_FIELDS as $field) {
            if (array_key_exists($field, $body)) {
                $value = $body[$field];
                $sent[$field] = is_string($value) && trim($value) === '' ? null : $value;
            }
        }

        $values = [];
        $errors = [];

        if (!$creating && empty($sent)) {
            $errors[] = 'Send at least one field to change.';
        }

        // iso: required, never null. Whether it names a language is the controller's lookup.
        if (!array_key_exists('iso', $sent)) {
            if ($creating) {
                $errors[] = 'iso is required.';
            }
        } elseif ($sent['iso'] === null) {
            $errors[] = $creating ? 'iso is required.' : 'iso cannot be empty.';
        } elseif (!is_string($sent['iso'])) {
            $errors[] = 'iso must be a string.';
        } elseif (!preg_match('/^[A-Za-z]{3}\z/', $sent['iso'])) {
            $errors[] = 'iso must be a 3-letter language code.';
        } else {
            $values['iso'] = strtolower($sent['iso']);
        }

        // alt_locale: optional, null clears.
        if (array_key_exists('alt_locale', $sent)) {
            if ($sent['alt_locale'] === null) {
                $values['alt_locale'] = null;
            } elseif (!is_string($sent['alt_locale'])) {
                $errors[] = 'alt_locale must be a string.';
            } else {
                $canonical = self::canonicalAltLocale($sent['alt_locale']);
                if ($canonical === null) {
                    $errors[] = 'alt_locale must be a locale code with a region or script, such as fr-CA or zh-Hant-HK.';
                } else {
                    $values['alt_locale'] = $canonical;
                }
            }
        }

        // name: required, never null or blank, varchar(191).
        if (!array_key_exists('name', $sent)) {
            if ($creating) {
                $errors[] = 'name is required.';
            }
        } elseif ($sent['name'] === null) {
            $errors[] = $creating ? 'name is required.' : 'name cannot be empty.';
        } elseif (!is_string($sent['name'])) {
            $errors[] = 'name must be a string.';
        } elseif (mb_strlen($sent['name'], 'UTF-8') > 191) {
            $errors[] = 'name may not be longer than 191 characters.';
        } else {
            $values['name'] = $sent['name'];
        }

        // thumbnail: optional, null clears, varchar(191). No URL check, as for plans.thumbnail.
        if (array_key_exists('thumbnail', $sent)) {
            if ($sent['thumbnail'] === null) {
                $values['thumbnail'] = null;
            } elseif (!is_string($sent['thumbnail'])) {
                $errors[] = 'thumbnail must be a string.';
            } elseif (mb_strlen($sent['thumbnail'], 'UTF-8') > 191) {
                $errors[] = 'thumbnail may not be longer than 191 characters.';
            } else {
                $values['thumbnail'] = $sent['thumbnail'];
            }
        }

        // description: optional, null clears, TEXT (65,535 bytes).
        if (array_key_exists('description', $sent)) {
            if ($sent['description'] === null) {
                $values['description'] = null;
            } elseif (!is_string($sent['description'])) {
                $errors[] = 'description must be a string.';
            } elseif (strlen($sent['description']) > 65535) {
                $errors[] = 'description is too long.';
            } else {
                $values['description'] = $sent['description'];
            }
        }

        // bible_id: optional, null clears, varchar(12) like user_bible. Whether it names a Bible is the lookup.
        if (array_key_exists('bible_id', $sent)) {
            if ($sent['bible_id'] === null) {
                $values['bible_id'] = null;
            } elseif (!is_string($sent['bible_id'])) {
                $errors[] = 'bible_id must be a string.';
            } elseif (mb_strlen($sent['bible_id'], 'UTF-8') > 12) {
                $errors[] = 'bible_id may not be longer than 12 characters.';
            } else {
                $values['bible_id'] = $sent['bible_id'];
            }
        }

        // plan_id: never set from the body. Echoing the plan's own id back (a JSON integer or a digit string) is
        // accepted and ignored, so a client can send back the object it read; anything else, null included, is
        // refused, so a body can never point a row at another plan.
        if (array_key_exists('plan_id', $body)) {
            $sent_plan_id = $body['plan_id'];
            $same_plan = (is_int($sent_plan_id) && $sent_plan_id === $plan_id)
                || (is_string($sent_plan_id) && preg_match('/^[0-9]+\z/', $sent_plan_id) && (int) $sent_plan_id === $plan_id);
            if (!$same_plan) {
                $errors[] = $creating ? 'plan_id does not match the plan in the URL.' : 'plan_id cannot be changed.';
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * An alt_locale in canonical BCP 47 case, or null when it is not a language with a script and/or a region:
     * language 2-3 letters, script 4 letters, region 2 letters or 3 digits, at most 12 characters. fr-ca → fr-CA,
     * zh-hant-hk → zh-Hant-HK, es-419 stays. A bare language (fr) has no row of its own: it is the iso row with no
     * alt_locale.
     *
     * @param string $value
     *
     * @return string|null
     */
    public static function canonicalAltLocale(string $value) : ?string
    {
        if (strlen($value) > 12
            || !preg_match('/^([A-Za-z]{2,3})(?:-([A-Za-z]{4}))?(?:-([A-Za-z]{2}|[0-9]{3}))?\z/', $value, $parts)
        ) {
            return null;
        }

        // An unmatched optional group is '' in the middle of $parts and missing at its end.
        $script = $parts[2] ?? '';
        $region = $parts[3] ?? '';
        if ($script === '' && $region === '') {
            return null;
        }

        return strtolower($parts[1])
            . ($script === '' ? '' : '-' . ucfirst(strtolower($script)))
            . ($region === '' ? '' : '-' . strtoupper($region));
    }

    /**
     * Whether iso and alt_locale name the same language, as far as the data can tell. Pure, so it is unit tested; the
     * two lookups are the caller's (isoAltLocaleError()).
     *
     * With L the language part of alt_locale: 3 letters must equal iso (zho with yue-HK is refused: Cantonese rows are
     * yue). 2 letters are judged through languages.iso1 in both directions: the language whose iso1 is L must be iso;
     * when no language has iso1 L (ar, fa, ko, sw...), iso's own iso1, when it has one, must be L. Otherwise accepted.
     *
     * @param string $iso
     * @param string $alt_locale canonical
     * @param string|null $iso_of_locale_language languages.iso where iso1 = L (only looked up for 2 letters)
     * @param string|null $iso1_of_iso iso's own non-null languages.iso1 (only needed when the first is null)
     *
     * @return bool
     */
    public static function isoMatchesAltLocale(
        string $iso,
        string $alt_locale,
        ?string $iso_of_locale_language,
        ?string $iso1_of_iso
    ) : bool {
        $iso = strtolower($iso);
        $language = self::localeLanguage($alt_locale);

        if (strlen($language) === 3) {
            return $language === $iso;
        }

        if ($iso_of_locale_language !== null) {
            return strtolower($iso_of_locale_language) === $iso;
        }

        if ($iso1_of_iso !== null) {
            return strtolower($iso1_of_iso) === $language;
        }

        return true;
    }

    /**
     * The 409 message. The id is null when a concurrent create won and the row could not be found afterwards.
     *
     * @param int $plan_id
     * @param string $iso
     * @param string|null $alt_locale
     * @param int|null $existing_id
     *
     * @return string
     */
    public static function duplicateMessage(int $plan_id, string $iso, ?string $alt_locale, ?int $existing_id) : string
    {
        $message = $alt_locale === null
            ? "Plan {$plan_id} already has a localization for iso {$iso} with no alt_locale"
            : "Plan {$plan_id} already has a localization for iso {$iso} and alt_locale {$alt_locale}";

        return $message . ($existing_id === null ? '.' : " (id {$existing_id}).");
    }

    /**
     * The plan, or the response to return: 404 for an unknown or deleted plan (SoftDeletes hides it), 403 for a
     * plan_editor who did not create it. The only way the actions load a plan.
     *
     * @param Request $request
     * @param mixed $plan_id
     *
     * @return Plan|\Illuminate\Http\JsonResponse
     */
    private function findEditablePlan(Request $request, $plan_id)
    {
        $plan = Plan::find((int) $plan_id);
        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        if ($deny = $this->denyUnlessCanEditPlan($request, $plan)) {
            return $deny;
        }

        return $plan;
    }

    /**
     * The localization with its plan, or the response to return: 404 for an unknown id or a row whose plan is
     * deleted (the eager load leaves plan null), 403 for a plan_editor who did not create the plan. The only way the
     * actions load a localization.
     *
     * @param Request $request
     * @param mixed $localization_id
     *
     * @return PlanLocalization|\Illuminate\Http\JsonResponse
     */
    private function findEditableLocalization(Request $request, $localization_id)
    {
        $localization = PlanLocalization::with('plan')->find((int) $localization_id);
        if (!$localization || !$localization->plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Localization Not Found');
        }

        if ($deny = $this->denyUnlessCanEditPlan($request, $localization->plan)) {
            return $deny;
        }

        return $localization;
    }

    /**
     * The decoded JSON object, or the 422 to return. $request->json() turns invalid JSON into an empty bag and a
     * top-level array into a list, so the raw body is decoded once to tell those from an object ({} is one).
     *
     * @param Request $request
     *
     * @return array|\Illuminate\Http\JsonResponse
     */
    private function jsonBody(Request $request)
    {
        if (!$request->isJson()) {
            return $this->setStatusCode(422)
                ->replyWithError('Send the fields as a JSON object with Content-Type: application/json.');
        }

        if (!json_decode($request->getContent()) instanceof \stdClass) {
            return $this->setStatusCode(422)->replyWithError('The request body must be a JSON object.');
        }

        // The cleaned bag: TrimStrings and ConvertEmptyStringsToNull have run on it.
        return $request->json()->all();
    }

    /**
     * Replaces iso and bible_id in $values with their stored spelling, and returns a message for each that matches
     * nothing (that value is then dropped from $values). Runs only on values that passed normalizeFields(); both are
     * content-DB reads.
     *
     * @param array $values
     *
     * @return array the 422 messages
     */
    private function resolveLookups(array &$values) : array
    {
        $errors = [];

        if (isset($values['iso'])) {
            // languages.iso is not unique (many rows share eng, fra, spa), so value() rather than a single-row read.
            $iso = Language::where('iso', $values['iso'])->value('iso');
            if ($iso === null) {
                $errors[] = 'iso does not match any language.';
                unset($values['iso']);
            } else {
                $values['iso'] = strtolower($iso);
            }
        }

        if (isset($values['bible_id'])) {
            // As for user_bible: bibles.id is unique and its collation case-insensitive, and the stored spelling is
            // what gets saved. No access-group or coverage check.
            $bible_id = Bible::whereId($values['bible_id'])->value('id');
            if ($bible_id === null) {
                $errors[] = 'bible_id does not match any Bible.';
                unset($values['bible_id']);
            } else {
                $values['bible_id'] = $bible_id;
            }
        }

        return $errors;
    }

    /**
     * The 422 message when iso and alt_locale are different languages, else null. Up to two indexed content-DB reads
     * (by the unique languages.iso1, then by languages.iso), only for a 2-letter locale language.
     *
     * @param string $iso as stored
     * @param string|null $alt_locale canonical, or null (nothing to check)
     *
     * @return string|null
     */
    private function isoAltLocaleError(string $iso, ?string $alt_locale) : ?string
    {
        if ($alt_locale === null) {
            return null;
        }

        $iso_of_locale_language = null;
        $iso1_of_iso = null;
        $language = self::localeLanguage($alt_locale);
        if (strlen($language) === 2) {
            $iso_of_locale_language = Language::where('iso1', $language)->value('iso');
            if ($iso_of_locale_language === null) {
                $iso1_of_iso = Language::where('iso', $iso)->whereNotNull('iso1')->value('iso1');
            }
        }

        if (self::isoMatchesAltLocale($iso, $alt_locale, $iso_of_locale_language, $iso1_of_iso)) {
            return null;
        }

        return "alt_locale {$alt_locale} does not belong to the language {$iso}.";
    }

    /**
     * The id of the plan's row with this iso and alt_locale (NULL matching NULL, as the unique key counts it; the
     * collation is case-insensitive), excluding $except_id; null when there is none.
     *
     * @param int $plan_id
     * @param string $iso
     * @param string|null $alt_locale
     * @param int|null $except_id
     *
     * @return int|null
     */
    private function duplicateOf(int $plan_id, string $iso, ?string $alt_locale, ?int $except_id = null) : ?int
    {
        $query = PlanLocalization::where('plan_id', $plan_id)->where('iso', $iso);
        if ($alt_locale === null) {
            $query->whereNull('alt_locale');
        } else {
            $query->where('alt_locale', $alt_locale);
        }
        if ($except_id !== null) {
            $query->where('id', '!=', $except_id);
        }

        $id = $query->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    private function notFeatured()
    {
        return $this->setStatusCode(422)
            ->replyWithError('This plan is not featured; only featured plans can have localizations.');
    }

    /**
     * One row as a bare object, as Plan Details replies. The status is set because the router reuses the controller
     * (and so $statusCode) across requests in one test run.
     *
     * @param PlanLocalization $localization
     *
     * @return mixed
     */
    private function replyWithLocalization(PlanLocalization $localization)
    {
        return $this->setStatusCode(200)->reply(
            fractal($localization, new PlanLocalizationTransformer(), new ArraySerializer())
        );
    }

    /**
     * Who changed a featured plan's presentation: ids only, never field values, the token or an email.
     *
     * @param string $action create, update or delete
     * @param Request $request
     * @param PlanLocalization $localization
     *
     * @return void
     */
    private function logWrite(string $action, Request $request, PlanLocalization $localization)
    {
        Log::info('plan_localization.' . $action, [
            'user_id'         => (int) $request->user()->id,
            'localization_id' => (int) $localization->id,
            'plan_id'         => (int) $localization->plan_id,
        ]);
    }

    /**
     * The language subtag of a canonical alt_locale, lower case.
     *
     * @param string $alt_locale
     *
     * @return string
     */
    private static function localeLanguage(string $alt_locale) : string
    {
        return strtolower(explode('-', $alt_locale)[0]);
    }
}
