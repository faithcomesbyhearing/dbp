<?php

namespace Tests\Integration;

use App\Models\Language\Language;
use App\Traits\AccessControlAPI;

class LanguageTest extends ApiV4NewTest
{
    use AccessControlAPI;

    /**
     * @category V4_API
     * @group    V4
     * @group    integration
     * @test
     */
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
