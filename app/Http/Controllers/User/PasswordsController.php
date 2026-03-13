<?php

namespace App\Http\Controllers\User;

use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Http\Controllers\APIController;
use App\Models\User\Project;
use App\Models\User\ProjectMember;
use Illuminate\Http\Request;

use App\Models\User\User;
use App\Models\User\PasswordReset;
use App\Mail\EmailPasswordReset;
use App\Models\User\Role;
use App\Traits\CheckProjectMembership;
use Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PasswordsController extends APIController
{
    use CheckProjectMembership;

    public function showResetForm(Request $request, $token = null)
    {
        $reset_request = PasswordReset::where('token', $token)->first();
        if (!$reset_request) {
            $browserLang = $request->getPreferredLanguage() ?? 'en';
            $language = $this->normalizeLocale($browserLang);
            app()->setLocale($language);
            return view('layouts.errors.broken')->with([
                'message'      => trans('auth.no_matching_token'),
                'hint'         => trans('auth.invalid_token_hint'),
                'action_url'   => route('password.request'),
                'action_label' => trans('auth.sendResetLink'),
            ]);
        }
        $language = $reset_request->language ?? 'eng';
        app()->setLocale($language);
        return view('auth.passwords.reset', compact('reset_request', 'language'));
    }

    public function showRequestForm(Request $request)
    {
        $browserLang = $request->getPreferredLanguage() ?? 'en';
        $language = $this->normalizeLocale($browserLang);
        app()->setLocale($language);
        $project = Project::where('name', 'Digital Bible Platform')->first();
        return view('auth.passwords.email', compact('project'));
    }

    /**
     *
     * @OA\Post(
     *     path="/users/password/email",
     *     tags={"Users"},
     *     summary="Trigger a reset email",
     *     description="",
     *     operationId="v4_internal_user.reset",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Information supplied for password reset",
     *         @OA\MediaType(mediaType="application/json",
     *             @OA\Schema (
     *                required={"email","project_id"},
     *                @OA\Property(property="email",        ref="#/components/schemas/User/properties/email"),
     *                @OA\Property(property="project_id",   ref="#/components/schemas/Project/properties/id"),
     *                @OA\Property(property="iso",          ref="#/components/schemas/Language/properties/iso"),
     *                @OA\Property(property="password",     ref="#/components/schemas/User/properties/password"),
     *                @OA\Property(property="new_password", ref="#/components/schemas/User/properties/password")
     *             ))
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(type="string"))
     *     )
     * )
     *
     * @param Request $request
     *
     * @return mixed
     *
     */
    public function triggerPasswordResetEmail(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return $this->setStatusCode(HttpResponse::HTTP_NOT_FOUND)->replyWithError(trans('api.users_errors_404'));
        }
        $project_id = checkParam('project_id', true);

        $connection = ProjectMember::with('project')->where(['user_id' => $user->id, 'project_id' => $project_id])->first();
        if (!$connection) {
            $role = Role::where('slug', 'user')->first();
            $connection = ProjectMember::create([
                'user_id'    => $user->id,
                'project_id' => $project_id,
                'role_id'    => $role->id ?? 'user'
            ]);
            $connection->load('project');
        }


        $iso = $request->input('iso', 'eng');
        $language = $this->normalizeLocale($iso);

        PasswordReset::where('email', $request->email)->delete();
        $generatedToken = PasswordReset::create([
            'email' => $request->email,
            'token' => Str::random(64),
            'reset_path' => $request->reset_path,
            'created_at' => Carbon::now(),
            'language' => $language
        ]);
        $user->token = $generatedToken->token;

        \Mail::to($user)->send(new EmailPasswordReset($user, $connection->project, $language));
        if (!$this->api) {
            return view('auth.passwords.email-sent');
        }

        return $this->reply(trans('api.email_send_successful'));
    }

    /**
     *
     * @OA\Post(
     *     path="/users/password/reset",
     *     tags={"Users"},
     *     summary="Reset the password for a user",
     *     description="This route handles resetting the password for a user that is a member of the project id provided
     *          If the password is known to the your users you can reset their passwords without the requirement to send
     *          them a verification email by setting the optional fields `password` and `new_password` fields within the
     *          request.",
     *     operationId="v4_internal_user.resetPassword",
     *     security={{"api_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Information supplied for password reset",
     *         @OA\MediaType(mediaType="application/json",
     *             @OA\Schema (
     *                required={"project_id","new_password","new_password_confirmation"},
     *                @OA\Property(property="email", ref="#/components/schemas/User/properties/email"),
     *                @OA\Property(property="project_id", ref="#/components/schemas/Project/properties/id"),
     *                @OA\Property(property="token_id", type="string",description="The token sent to the user's email"),
     *                @OA\Property(property="old_password", ref="#/components/schemas/User/properties/password"),
     *                @OA\Property(property="new_password", ref="#/components/schemas/User/properties/password"),
     *                @OA\Property(property="new_password_confirmation", ref="#/components/schemas/User/properties/password")
     *             ))
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_internal_user_index"))
     *     )
     * )
     *
     * @param Request $request
     * @return mixed
     *
     */
    public function validatePasswordReset(Request $request)
    {
        $user = $request->user();
        $is_logged = !empty($user);

        // Validate Project / User Connection
        if ($is_logged && !$this->compareProjects($user->id, $this->key)) {
            return $this->setStatusCode(HttpResponse::HTTP_UNAUTHORIZED)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $validator = Validator::make($request->all(), [
            'new_password'     => 'confirmed|required|min:8',
            'old_password'     =>  $is_logged ? 'required' : '',
            'email'            => $is_logged ? 'email' : 'required|email',
            'project_id'       => 'exists:dbp_users.projects,id',
            'token_id'         => [
                $is_logged || $request->old_password ? '' : 'required',
                Rule::exists('dbp_users.password_resets', 'token')->where(function ($query) use ($request) {
                    $query->where('email', $request->email);
                })
            ]
        ]);

        if ($validator->fails()) {
            $token = $request->token_id;
            $errors = $validator->errors();

            if ($this->api) {
                return $this->setStatusCode(HttpResponse::HTTP_UNAUTHORIZED)->replyWithError($errors);
            }

            return view('auth.passwords.reset', compact('token', 'errors'));
        }

        $user = $is_logged ? $user : User::where('email', $request->email)->first();

        if (!$user) {
            return $this->setStatusCode(HttpResponse::HTTP_NOT_FOUND)->replyWithError(trans('api.users_errors_404'));
        }

        $password_match = \Hash::check($request->old_password, $user->password);
        if ($request->old_password && !$password_match) {
            return $this->setStatusCode(HttpResponse::HTTP_UNAUTHORIZED)->replyWithError(trans('auth.failed'));
        }

        $new_password = $request->new_password;
        $user->password = \Hash::make($new_password);
        $user->save();

        $reset_path = url('/');
        if ($request->token_id) {
            $reset = PasswordReset::where('email', $user->email)->where('token', $request->token_id)->first();
            if (!empty($reset)) {
                if ($reset->reset_path && $this->isSafeRedirectUrl($reset->reset_path)) {
                    $reset_path = $reset->reset_path;
                }
                $reset->delete();
            }
        }

        if ($this->api) {
            unset($user->api_token);
            return $this->setStatusCode(HttpResponse::HTTP_OK)->reply($user);
        }
        return view('auth.passwords.reset-successful', compact('reset_path'));
    }

    private function normalizeLocale(string $iso): string
    {
        $base = strtolower(preg_split('/[-_]/', $iso)[0]);
        $map = [
            // 2-letter -> directory name
            'en' => 'eng', 'es' => 'spa', 'fr' => 'fre', 'ar' => 'arb',
            'bn' => 'ben', 'da' => 'dan', 'de' => 'deu', 'el' => 'ell',
            'fa' => 'fas', 'ha' => 'hau', 'he' => 'heb', 'hi' => 'hin',
            'id' => 'ind', 'ig' => 'ibo', 'it' => 'ita', 'ja' => 'jpn',
            'jv' => 'jav', 'ko' => 'kor', 'ku' => 'kmr', 'la' => 'lat',
            'lt' => 'lit', 'ml' => 'mal', 'ms' => 'zlm', 'nl' => 'nld',
            'pt' => 'por', 'ro' => 'ron', 'ru' => 'rus', 'sv' => 'swe',
            'ta' => 'tam', 'te' => 'tel', 'th' => 'tha', 'tr' => 'tur',
            'ur' => 'urd', 'vi' => 'vie', 'yo' => 'yor', 'zh' => 'cmn',
            'az' => 'aze', 'uk' => 'ukr',
            // 3-letter passthrough
            'eng' => 'eng', 'spa' => 'spa', 'fre' => 'fre', 'fra' => 'fre',
            'arb' => 'arb', 'ben' => 'ben', 'cmn' => 'cmn', 'deu' => 'deu',
            'hin' => 'hin', 'por' => 'por', 'rus' => 'rus', 'tur' => 'tur',
            'jpn' => 'jpn', 'kor' => 'kor', 'ita' => 'ita', 'vie' => 'vie',
            'tha' => 'tha', 'ind' => 'ind', 'hau' => 'hau', 'yor' => 'yor',
            'ibo' => 'ibo', 'fas' => 'fas', 'heb' => 'heb', 'urd' => 'urd',
            'tam' => 'tam', 'tel' => 'tel', 'mal' => 'mal', 'ell' => 'ell',
            'nld' => 'nld', 'ron' => 'ron', 'swe' => 'swe', 'dan' => 'dan',
            'lit' => 'lit', 'jav' => 'jav', 'kmr' => 'kmr', 'zlm' => 'zlm',
            'lat' => 'lat', 'aze' => 'aze', 'ukr' => 'ukr', 'shu' => 'arb',
        ];
        return $map[$base] ?? 'eng';
    }

    private function isSafeRedirectUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || isset($parsed['scheme']) && !in_array($parsed['scheme'], ['http', 'https'], true)) {
            return false;
        }
        if (isset($parsed['host'])) {
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            return $parsed['host'] === $appHost;
        }
        return true;
    }
}
