<?php

namespace App\Transformers;

use Spatie\Fractalistic\ArraySerializer;

class EmbeddedArraySerializer extends ArraySerializer
{
    /**
     * Serialize a collection.
     *
     * @param string $resourceKey
     * @param array  $data
     *
     * @return array
     */
    public function collection(?string $resourceKey, array $data) : array
    {
        return ['_embedded' => $data];
    }

    /**
     * Serialize an item.
     *
     * @param string $resourceKey
     * @param array  $data
     *
     * @return array
     */
    public function item(?string $resourceKey, array $data) : array
    {
        return ['_embedded' => $data];
    }

    /**
     * Serialize null resource.
     *
     * @return array
     */
    public function null() : ?array
    {
        return ['_embedded' => []];
    }
}
