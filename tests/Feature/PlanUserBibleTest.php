<?php

namespace Tests\Feature;

use App\Http\Controllers\Plan\PlansController;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for PlansController::normalizeUserBible(), the DB-free part of the `user_bible`
 * check used by POST /plans/{id}/start and PUT /plans/{id}/bible (PBI 102552).
 *
 * The value reaches it through checkParam('user_bible'), which has already dropped null, "",
 * "0", 0, false and empty arrays, and body/query strings arrive trimmed by TrimStrings. Header
 * values are not trimmed by the framework, so normalizeUserBible() trims and treats a value that
 * is blank after trimming as not sent. Non-strings and values longer than the varchar(12) column
 * are rejected with the 422 message before any query runs. Whether the id matches a Bible is a
 * DB question and is covered by tests/Integration/PlanUserBibleTest.php.
 *
 * Runs without a database or the Laravel container: it only touches a static method.
 *
 * @group plans
 */
class PlanUserBibleTest extends TestCase
{
    public static function notSentProvider(): array
    {
        return [
            'nothing'                       => [null],
            'empty string'                  => [''],
            'spaces only (header)'          => ['   '],
            'tabs and newlines (header)'    => ["\t\n "],
        ];
    }

    /**
     * @dataProvider notSentProvider
     * @test
     */
    public function blankValuesCountAsNotSent($value)
    {
        $this->assertNull(PlansController::normalizeUserBible($value));
    }

    public static function acceptedProvider(): array
    {
        return [
            'bible id'                         => ['ENGESV', 'ENGESV'],
            'surrounding spaces are trimmed'   => [' ENGESV ', 'ENGESV'],
            'lower case is passed on as sent'  => ['engesv', 'engesv'],
            'exactly 12 characters'            => ['ABCDEFGHIJKL', 'ABCDEFGHIJKL'],
            '12 multibyte characters'          => [str_repeat('é', 12), str_repeat('é', 12)],
            // Shape is fine; the DB lookup rejects these (integration test).
            'unknown id'                       => ['XXXXXX', 'XXXXXX'],
            'the word null'                    => ['null', 'null'],
        ];
    }

    /**
     * Case is not normalized here: the DB lookup returns the Bible's own spelling, which is what
     * gets stored.
     *
     * @dataProvider acceptedProvider
     * @test
     */
    public function wellFormedValuesAreTrimmedAndPassedOn(string $value, string $expected)
    {
        $this->assertSame($expected, PlansController::normalizeUserBible($value));
    }

    public static function notAStringProvider(): array
    {
        return [
            'JSON number'  => [123],
            'JSON true'    => [true],
            'JSON float'   => [1.5],
            'JSON list'    => [['ENGESV']],
            'JSON object'  => [['a' => 1]],
        ];
    }

    /**
     * The type is checked first, so a client sending a number or array gets a clear 422 instead
     * of a TypeError (HTTP 500) from trim().
     *
     * @dataProvider notAStringProvider
     * @test
     */
    public function nonStringsAreRejected($value)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('user_bible must be a string.');
        PlansController::normalizeUserBible($value);
    }

    public static function tooLongProvider(): array
    {
        return [
            '13 characters'                     => ['ABCDEFGHIJKLM'],
            'a fileset id with a long suffix'   => ['ENGESVN2DA16-opus'],
            '13 multibyte characters'           => [str_repeat('é', 13)],
            'length is measured after trimming' => ['  ABCDEFGHIJKLM  '],
        ];
    }

    /**
     * @dataProvider tooLongProvider
     * @test
     */
    public function valuesLongerThanTheColumnAreRejected(string $value)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('user_bible may not be longer than 12 characters.');
        PlansController::normalizeUserBible($value);
    }

    /**
     * Twelve characters with padding is fine: the padding is trimmed before the length check.
     *
     * @test
     */
    public function paddingDoesNotCountTowardsTheLength()
    {
        $this->assertSame('ABCDEFGHIJKL', PlansController::normalizeUserBible('  ABCDEFGHIJKL  '));
    }
}
