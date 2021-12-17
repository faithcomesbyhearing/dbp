<?php

namespace App\Models\Language;

use Illuminate\Database\Eloquent\Model;

class AlphabetNumeralSystem extends Model
{
    protected $table = 'alphabet_numeral_systems';
    protected $connection = 'dbp';
    public $incrementing = false;


    /**
     * @OA\Property(
     *     title="Numeral system ID",
     *     description="numeral system ID using for the languages",
     *     type="string",
     *     minLength=3,
     *     example="Cans",
     *     @OA\ExternalDocumentation(
     *         description="For more info please refer to the Unicode Consortium",
     *         url="https://http://www.unicode.org/iso15924/"
     *     ),
     * )
     *
     * @var string $numeral_system_id
     */
    protected $numeral_system_id;
}
