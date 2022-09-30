<?php

namespace App\Transformers\Serializers;

use Spatie\Fractalistic\ArraySerializer;

class NoteArraySerializer extends ArraySerializer
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
        return ['notes' => $data];
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
        return ['notes' => $data];
    }

    /**
     * Serialize null resource.
     *
     * @return array
     */
    public function null() : array
    {
        return ['notes' => []];
    }
}
