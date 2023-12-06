<?php

/*
 * This file is part of the League\Fractal package.
 *
 * (c) Phil Sturgeon <me@philsturgeon.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Transformers\Serializers;

use Spatie\Fractalistic\ArraySerializer;

class BookmarkArraySerializer extends ArraySerializer
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
        return ['bookmarks' => $data];
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
        return ['bookmarks' => $data];
    }

    /**
     * Serialize null resource.
     *
     * @return array
     */
    public function null() : ?array
    {
        return ['bookmarks' => []];
    }
}
