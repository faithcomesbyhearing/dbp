<?php

namespace App\Transformers;

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
        switch ($this->route) {
            case 'v4_bible_filesets_download.list':
                return [
                    'type'      => (string) $fileset->type,
                    'language'  => (string) $fileset->language,
                    'licensor'  => (string) $fileset->licensor,
                    'filesetid' => (string) $fileset->filesetid,
                ];
            default:
                return [];
        }
    }
}
