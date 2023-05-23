<?php

namespace App\Validators;

use Illuminate\Support\Facades\DB;

class AccountValidator
{
    public function validate($attribute, $value, $parameters, $validator)
    {
        $parameters_connection = \explode('.', $parameters[0]);
        $connection = $parameters_connection[0];
        $table = $parameters_connection[1];
        $provider_id_value = $validator->getData()[$parameters[2]];

        $social_provider_columns = [
            'social_provider_user_id' => 'provider_user_id',
            'social_provider_id' => 'provider_id'
        ];
        $query = DB::connection($connection)
            ->table($table)
            ->where($social_provider_columns[$attribute], $value)
            ->where($social_provider_columns[$parameters[2]], $provider_id_value);

        return $query->count() === 0;
    }
}
