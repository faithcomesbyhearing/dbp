<?php

namespace App\Transformers;

use App\Models\Organization\Organization;

class BibleFileSetsDownloadTransFormer extends BaseTransformer
{

    /**
     * A Fractal transformer.
     *
     * @param $fileset
     *
     * @return array
     */
    public function transform($fileset)
    {
        if($fileset->licensorid == Organization::SIL_LICENSOR_ID) {
            $fileset->licensor = Organization::USED_WITH_PERMISSION;
        }

        switch ($this->route) {
            case 'v4_bible_filesets_download.list':
                return [
                    'type'       => (string) $fileset->type,
                    'language'   => (string) $fileset->language,
                    'licensor'   => (string) $fileset->licensor,
                    'fileset_id' => (string) $fileset->filesetid,
                ];
            default:
                return [];
        }
    }
}
