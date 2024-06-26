<?php

namespace App\Transformers;

class UserTransformer extends BaseTransformer
{
    public function transform($user)
    {
        switch ($this->version) {
            case 4:
            default:
                return $this->transformForV4($user);
        }
    }

    /**
     *
     * @see Controller: \App\Http\Controllers\User\UsersControllerV2::index
     * @see https://api.dbp.test/users?key=test_key&v=4
     *
     * @OA\Schema (
     *    title="v4_internal_user_index",
     *    type="object",
     *    schema="v4_internal_user_index",
     *    description="The v4 user index response",
     *    @OA\Xml(name="v4_internal_user_index"),
     *    @OA\Property(property="data", type="array",
     *     @OA\Items(
     *        @OA\Property(property="id",       ref="#/components/schemas/User/properties/id"),
     *        @OA\Property(property="name",     ref="#/components/schemas/User/properties/name"),
     *        @OA\Property(property="email",    ref="#/components/schemas/User/properties/email")
     *     )
     *    )
     *  )
     *
     * @OA\Schema (
     *    type="object",
     *    schema="v4_internal_user_show",
     *    description="The v4 user show response",
     *    title="v4_internal_user_show",
     *    @OA\Xml(name="v4_internal_user_show"),
     *    @OA\Property(property="data", type="object",
     *       @OA\Property(property="id",       ref="#/components/schemas/User/properties/id"),
     *       @OA\Property(property="name",     ref="#/components/schemas/User/properties/name"),
     *       @OA\Property(property="nickname", ref="#/components/schemas/User/properties/nickname"),
     *       @OA\Property(property="avatar",   ref="#/components/schemas/User/properties/avatar"),
     *       @OA\Property(property="email",    ref="#/components/schemas/User/properties/email"),
     *       @OA\Property(property="profile",  ref="#/components/schemas/Profile"),
     *       @OA\Property(property="organizations",  ref="#/components/schemas/Organization"),
     *       @OA\Property(property="accounts", type="object",description="The unique identifier for a user's connection to the api and the means of that connection",example={"facebook":"1903random6321","cookie": "43190crumbles1023"}),
     *    )
     * )
     */
    public function transformForV4($user)
    {
        switch ($this->route) {
            /**
             * schema="v4_internal_user_index",
             */
            case 'v4_internal_user.index':
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ];

            case 'v4_internal_user.store':
                return [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'nickname'  => $user->nickname,
                    'avatar'    => $user->avatar,
                    'email'     => $user->email,
                    'profile'   => $user->profile,
                    'organizations' => $user->organizations,
                    'accounts'  => $user->accounts,
                    'keys'      => $user->keys,
                    'api_token' => $user->api_token,
                    'freshchat_restore_id' => $user->freshchat_restore_id,
                ];

            /**
             *    schema="v4_internal_user_show",
             */
            case 'v4_internal_user.show':
            default:
                return [
                'id'        => $user->id,
                'name'      => $user->name,
                'nickname'  => $user->nickname,
                'avatar'    => $user->avatar,
                'email'     => $user->email,
                'profile'   => $user->profile,
                'organizations' => $user->organizations,
                'accounts'  => $user->accounts,
                'freshchat_restore_id' => $user->freshchat_restore_id,
                ];
        }
    }
}
