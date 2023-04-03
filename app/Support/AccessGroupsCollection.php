<?php

namespace App\Support;

use Illuminate\Support\Collection;

class AccessGroupsCollection extends Collection
{
    /**
     * Get all of the items in the collection as a single string delimited by |.
     *
     * @return string
     */
    public function toString() : string
    {
        return $this->implode('|');
    }
}
