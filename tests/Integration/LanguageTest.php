<?php

namespace Tests\Integration;

use App\Models\Language\Language;
use App\Traits\AccessControlAPI;

class LanguageTest extends ApiV4NewTest
{
    use AccessControlAPI;

    /**
     * @category V4_API
     */
    #[\PHPUnit\Framework\Attributes\Group('V4')]
    #[\PHPUnit\Framework\Attributes\Group('integration')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function languagesRolvCodeNewColumn()
    {
        $language = Language::select('*')
            ->limit(1)
            ->first();

        $exists_rolv_code_column =\Schema::connection('dbp')->hasColumn('languages', 'rolv_code');

        $this->assertEquals($exists_rolv_code_column, true);
        $this->assertEquals($language->rolv_code, '');
    }
}
