<?php

namespace App\Transformers\Factbook;

use League\Fractal\TransformerAbstract;

class TransportationTransformer extends TransformerAbstract
{
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform($country_transportation)
    {
        return $country_transportation;
    }
}
