<?php

namespace App\Models\Traits;

trait ModelBase
{
    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        $key = $this->getKeyName();

        if (is_array($key)) {
            return $this->getKeyName();
        }

        return parent::getKey();
    }
}
