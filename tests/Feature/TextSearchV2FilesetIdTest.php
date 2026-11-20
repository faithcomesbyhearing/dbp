<?php

namespace Tests\Feature;

use App\Http\Controllers\Bible\TextControllerV2;
use PHPUnit\Framework\TestCase;

/**
 * PBI 106796 (H-01): the v2 text search must not accept a fileset / dam id that
 * contains a quote or an SQL expression. Pure unit test, no database needed.
 */
class TextSearchV2FilesetIdTest extends TestCase
{
    /**
     * @group V2
     * @group travis
     * @test
     */
    public function acceptsPlainFilesetAndDamIds()
    {
        foreach (['ENGESV', 'ENGESVN2ET', 'ENGESVO1ET', 'ENGKJVN_ET-mobile', 'AAIWBTN2ET', 'engesv'] as $id) {
            $this->assertTrue(TextControllerV2::isValidFilesetIdFormat($id), "expected '$id' to be accepted");
        }
    }

    /**
     * @group V2
     * @group travis
     * @test
     */
    public function rejectsQuotesAndSqlExpressions()
    {
        $invalid = [
            "ENGESV'",
            "ENGESV' OR '1'='1",
            "ENGESV'; DROP TABLE bible_verses; --",
            'ENGESV" OR 1=1',
            'ENGESV) UNION SELECT 1 --',
            'ENGESV OR 1=1',
            "ENGESV\\",
            'ENGESV;',
            'ENGESV%',
            'ENG ESV',
            '',
            str_repeat('A', 33),
        ];
        foreach ($invalid as $id) {
            $this->assertFalse(TextControllerV2::isValidFilesetIdFormat($id), "expected '$id' to be rejected");
        }
        $this->assertFalse(TextControllerV2::isValidFilesetIdFormat(['ENGESV']));
        $this->assertFalse(TextControllerV2::isValidFilesetIdFormat(null));
    }
}
