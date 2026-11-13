<?php

namespace Tests\Feature;

use App\Http\Controllers\Plan\PlanCompatibilityController;
use App\Services\Plans\PlanCompatibilityCalculator;
use App\Services\Plans\PlanCompatibilityService;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for POST /plans/compatibility (API task 102553), without a database or the Laravel container:
 *  - Samples A to H of the request and response contract attached to API task 102553 (revision 5,
 *    -REQUEST-AND-RESPONSE-2026-11-13.md), whose JSON is copied here verbatim, reproduced exactly (counts, statuses,
 *    key order) from fixture plans and fixture pieces through the controller's own request check and assembly
 *    (normalizeRequest, bibleResults, entries). Sample E runs with and without include_opus, Sample F with and without
 *    allow_video_only, Sample G with exact_threshold and Sample H with allow_chapter_audio;
 *  - Sam's examples of audio as a whole, and of audio outranking text (design section 12);
 *  - the single rules, the options and the roll-ups of PlanCompatibilityCalculator, with revision 5's USX and JSON
 *    text, combined verses, exact_threshold's upper side (no lower side) and allow_chapter_audio.
 *
 * The fixtures follow the contract's made-up Bibles. Verse counts are the real ones for Matthew and the Psalms used,
 * and 31 for every chapter of the other books.
 *
 * @group plans
 */
class PlanCompatibilityCalculatorTest extends TestCase
{
    private const MAT_VERSES = [
        25, 23, 17, 25, 48, 34, 29, 34, 38, 42, 30, 50, 58, 36, 39, 28, 27, 35, 30, 34, 46, 46, 39, 51, 46, 75, 66, 20,
    ];
    private const PSA_VERSES = [23 => 6, 27 => 14, 34 => 22, 46 => 11, 91 => 16, 119 => 176];

    private const OT = ['GEN' => [1, 50], 'PSA' => [1, 150]];
    private const NT = ['MAT' => [1, 28]];

    private const ITEM_88231 = 88231;
    private const ITEM_88201 = 88201;
    private const ITEM_90101 = 90101;

    private int $next_item_id = 1000;

    // ---------------------------------------------------------------------------------------------------------------
    // Fixtures

    private static function verseCount(string $book, int $chapter) : int
    {
        if ($book === 'MAT') {
            return self::MAT_VERSES[$chapter - 1];
        }
        if ($book === 'PSA') {
            return self::PSA_VERSES[$chapter] ?? 20;
        }

        return 31;
    }

    /** book => [first chapter, last chapter] as a list of chapters per book. */
    private static function chapters(array $books) : array
    {
        $chapters = [];
        foreach ($books as $book => [$first, $last]) {
            $chapters[$book] = range($first, $last);
        }

        return $chapters;
    }

    /** text_plain pieces: one range per chapter, from verse 1 to its last verse. */
    private static function textPieces(array $books) : array
    {
        $pieces = [];
        foreach (self::chapters($books) as $book => $chapters) {
            foreach ($chapters as $chapter) {
                $pieces[$book][] = [$chapter, 1, $chapter, self::verseCount($book, $chapter)];
            }
        }

        return $pieces;
    }

    /** A file per chapter (DA), or an SA stream per chapter: verse 1 to the end of the chapter. */
    private static function chapterPieces(array $books) : array
    {
        $pieces = [];
        foreach (self::chapters($books) as $book => $chapters) {
            foreach ($chapters as $chapter) {
                $pieces[$book][] = [$chapter, 1, $chapter, null];
            }
        }

        return $pieces;
    }

    /** Three clips per chapter of Matthew, split at thirds: MAT 5's first clip is 5:1-16. */
    private static function matthewClips() : array
    {
        $pieces = [];
        foreach (range(1, 28) as $chapter) {
            $verses = self::verseCount('MAT', $chapter);
            $third = intdiv($verses, 3);
            $pieces['MAT'][] = [$chapter, 1, $chapter, $third];
            $pieces['MAT'][] = [$chapter, $third + 1, $chapter, 2 * $third];
            $pieces['MAT'][] = [$chapter, 2 * $third + 1, $chapter, $verses];
        }

        return $pieces;
    }

    private static function fileset(
        string $id,
        string $set_type_code,
        array $pieces,
        ?string $segmentation_type = 'chapter',
        bool $is_opus = false
    ) : array {
        return [
            'hash_id'           => substr(md5($id), 0, 12),
            'id'                => $id,
            'set_type_code'     => $set_type_code,
            'segmentation_type' => $segmentation_type,
            'is_opus'           => $is_opus,
            'pieces'            => $pieces,
        ];
    }

    /** The contract's Bibles. ENGXYZ doesn't exist, so it has no visible fileset. */
    private static function bibles() : array
    {
        $obt_sections = ['PSA' => [
            [23, 1, 23, 3], [23, 4, 23, 6],
            [27, 1, 27, 4], [27, 5, 27, 14],
            [34, 1, 34, 7], [34, 8, 34, 22],
            [46, 1, 46, 5], [46, 6, 46, 11],
            [91, 1, 91, 10], [91, 11, 91, 16],
            // Songs of Ascent: PSA 120, 123, 127, 131 and 133 are one section each; 121 shares a section with 122; the
            // others are split in two.
            [120, 1, 120, 7],
            [121, 1, 122, 2], [122, 3, 122, 9],
            [123, 1, 123, 4],
            [124, 1, 124, 5], [124, 6, 124, 8],
            [125, 1, 125, 3], [125, 4, 125, 5],
            [126, 1, 126, 3], [126, 4, 126, 6],
            [127, 1, 127, 5],
            [128, 1, 128, 4], [128, 5, 128, 6],
            [129, 1, 129, 4], [129, 5, 129, 8],
            [130, 1, 130, 4], [130, 5, 130, 8],
            [131, 1, 131, 3],
            [132, 1, 132, 10], [132, 11, 132, 18],
            [133, 1, 133, 3],
            [134, 1, 134, 2], [134, 3, 134, 3],
        ]];

        return [
            'ENGESV' => [
                self::fileset('ENGESV', 'text_plain', self::textPieces(self::OT + self::NT)),
                self::fileset('ENGESVO1DA', 'audio', self::chapterPieces(self::OT)),
                self::fileset('ENGESVN1DA', 'audio', self::chapterPieces(self::NT)),
                self::fileset('ENGESVN1SA', 'audio_stream', self::chapterPieces(self::NT)),
                self::fileset('ENGESVO2DA', 'audio_drama', self::chapterPieces(self::OT)),
                self::fileset('ENGESVN2DA', 'audio_drama', self::chapterPieces(self::NT)),
                self::fileset('ENGESVP2DV', 'video_stream', self::matthewClips(), 'section'),
            ],
            'NTONLY' => [
                self::fileset('NTONLYN_ET', 'text_plain', self::textPieces(self::NT)),
                self::fileset('NTONLYN1DA', 'audio', self::chapterPieces(self::NT)),
                self::fileset('NTONLYN2DA', 'audio_drama', self::chapterPieces(self::NT)),
            ],
            'DAONLY' => [
                self::fileset('DAONLYO1DA', 'audio', self::chapterPieces(self::OT)),
                self::fileset('DAONLYN1DA', 'audio', self::chapterPieces(self::NT)),
            ],
            'OBTSEC' => [
                self::fileset('OBTSECO1DA', 'audio', $obt_sections, 'section'),
            ],
            'OPUSDR' => [
                self::fileset('OPUSDRN_ET', 'text_plain', self::textPieces(self::NT)),
                self::fileset('OPUSDRN1DA', 'audio', self::chapterPieces(self::NT)),
                self::fileset('OPUSDRO2DA-opus16', 'audio_drama', self::chapterPieces(self::OT), 'chapter', true),
            ],
            'MATVID' => [
                self::fileset('MATVIDN1DA', 'audio', self::chapterPieces(self::NT)),
                self::fileset('MATVIDP2DV', 'video_stream', self::matthewClips(), 'section'),
            ],
            'ENGXYZ' => [],
        ];
    }

    private function item(string $book, int $chapter_start, $verse_start = null, $verse_end = null, ?int $id = null, ?int $chapter_end = null) : array
    {
        return [
            'id'            => $id ?? $this->next_item_id++,
            'book_id'       => $book,
            'chapter_start' => $chapter_start,
            'chapter_end'   => $chapter_end ?? $chapter_start,
            'verse_start'   => $verse_start === null ? null : (string) $verse_start,
            'verse_end'     => $verse_end === null ? null : (string) $verse_end,
        ];
    }

    /**
     * The contract's plans: 401 (60 items), 402 (10 Psalm ranges), 403 (36 Matthew ranges, 4 whole chapters; item
     * 90101 is MAT 1:1-17), 404 (PSA 120-134, whole chapters).
     */
    private function plans() : array
    {
        $plan_401 = [];
        foreach (range(1, 26) as $chapter) {
            $plan_401[] = $this->item('GEN', $chapter, null, null, $chapter === 1 ? self::ITEM_88201 : null);
        }
        foreach ([[1, 40], [41, 88], [89, 128], [129, 176]] as [$start, $end]) {
            $plan_401[] = $this->item('PSA', 119, $start, $end);
        }
        foreach (range(1, 28) as $chapter) {
            if ($chapter === 5) {
                $plan_401[] = $this->item('MAT', 5, 1, 12, self::ITEM_88231);
                $plan_401[] = $this->item('MAT', 5, 13, 48);
            } elseif ($chapter === 6) {
                $plan_401[] = $this->item('MAT', 6, 1, 18);
                $plan_401[] = $this->item('MAT', 6, 19, 34);
            } else {
                $plan_401[] = $this->item('MAT', $chapter);
            }
        }

        $plan_402 = [];
        foreach ([[23, 1, 3], [23, 4, 6], [27, 1, 6], [27, 7, 14], [34, 1, 10], [34, 11, 22], [46, 1, 7], [46, 8, 11], [91, 1, 8], [91, 9, 16]] as [$chapter, $start, $end]) {
            $plan_402[] = $this->item('PSA', $chapter, $start, $end);
        }

        $plan_403 = [$this->item('MAT', 1, 1, 17, self::ITEM_90101), $this->item('MAT', 1, 18, 25)];
        foreach (array_merge(range(2, 11), range(16, 22)) as $chapter) {
            $half = intdiv(self::verseCount('MAT', $chapter), 2);
            $plan_403[] = $this->item('MAT', $chapter, 1, $half);
            $plan_403[] = $this->item('MAT', $chapter, $half + 1, self::verseCount('MAT', $chapter));
        }
        foreach ([12, 13, 14, 15] as $chapter) {
            $plan_403[] = $this->item('MAT', $chapter);
        }

        $plan_404 = [];
        foreach (range(120, 134) as $chapter) {
            $plan_404[] = $this->item('PSA', $chapter);
        }

        return [401 => $plan_401, 402 => $plan_402, 403 => $plan_403, 404 => $plan_404];
    }

    /**
     * The response for a request, as the controller builds it: the request check, unknown plans and Bibles to
     * meta.not_found, step 1 on each Bible's visible filesets, then bibleResults() per Bible and entries().
     */
    private function respond(array $request, array $plans, array $bibles) : array
    {
        $normalized = PlanCompatibilityController::normalizeRequest($request);
        $this->assertSame([], $normalized['errors']);
        $values = $normalized['values'];

        $calculator = new PlanCompatibilityCalculator(
            $values['include_video'],
            $values['include_opus'],
            $values['allow_video_only'],
            $values['exact_threshold'],
            $values['allow_chapter_audio']
        );

        $found_plans = [];
        $not_found_plan_ids = [];
        foreach ($values['plan_ids'] as $plan_id) {
            if (isset($plans[$plan_id])) {
                $found_plans[$plan_id] = $plans[$plan_id];
            } else {
                $not_found_plan_ids[] = $plan_id;
            }
        }
        if ($values['playlist_item_ids'] !== null) {
            $this->assertSame([], PlanCompatibilityController::unknownPlaylistItemIds($found_plans, $values['playlist_item_ids']));
        }

        $bible_results = [];
        $not_found_bible_ids = [];
        foreach ($values['bible_ids'] as $bible_id) {
            $visible = $bibles[$bible_id] ?? [];
            if (empty($visible)) {
                $not_found_bible_ids[] = $bible_id;
                continue;
            }
            $kept = array_values(array_filter($visible, [$calculator, 'keepsFileset']));
            $bible_results[] = [
                $bible_id,
                PlanCompatibilityController::bibleResults(
                    $calculator,
                    $found_plans,
                    $calculator->prepareBible($kept, PlanCompatibilityController::planBooks($found_plans)),
                    $values['playlist_item_ids']
                ),
            ];
        }

        return [
            'data' => PlanCompatibilityController::entries($found_plans, $bible_results),
            'meta' => ['not_found' => ['plan_ids' => $not_found_plan_ids, 'bible_ids' => $not_found_bible_ids]],
        ];
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The contract's samples

    /**
     * @dataProvider contractSamples
     */
    public function testContractSampleIsReproducedExactly(string $request_json, string $response_json)
    {
        $request = json_decode($request_json, true);
        $expected = json_decode($response_json, true);
        $this->assertIsArray($request);
        $this->assertIsArray($expected);

        $actual = $this->respond($request, $this->plans(), self::bibles());

        // assertSame on arrays also checks the key order.
        $this->assertSame($expected, $actual);
    }

    public static function contractSamples() : array
    {
        // Copied verbatim from the contract file's Request and Response blocks.
        return [
            'Sample A, Passage Not Available with video' => [
                <<<'JSON'
{
  "plan_ids": [401],
  "bible_ids": ["ENGESV", "NTONLY", "ENGXYZ"],
  "playlist_item_ids": [88231, 88201],
  "include_video": true
}
JSON,
                <<<'JSON'
{
  "data": [
    {
      "plan_id": 401,
      "bible_id": "ENGESV",
      "status": "mismatch",
      "items": {"total": 60, "exact": 56, "approximate": 0, "mismatch": 4, "missing": 0},
      "formats": {
        "text": {
          "status": "full",
          "items": {"total": 60, "exact": 60, "approximate": 0, "mismatch": 0, "missing": 0}
        },
        "audio": {
          "status": "mismatch",
          "items": {"total": 60, "exact": 56, "approximate": 0, "mismatch": 4, "missing": 0},
          "non_drama": {
            "status": "mismatch",
            "items": {"total": 60, "exact": 56, "approximate": 0, "mismatch": 4, "missing": 0}
          },
          "drama": {
            "status": "mismatch",
            "items": {"total": 60, "exact": 52, "approximate": 0, "mismatch": 8, "missing": 0}
          }
        },
        "video": {
          "status": "partial",
          "items": {"total": 60, "exact": 0, "approximate": 30, "mismatch": 0, "missing": 30}
        }
      },
      "playlist_items": [
        {
          "playlist_item_id": 88231,
          "status": "full",
          "formats": {
            "text": "exact",
            "audio": {"status": "full", "non_drama": "exact", "drama": "mismatch"},
            "video": "approximate"
          }
        },
        {
          "playlist_item_id": 88201,
          "status": "full",
          "formats": {
            "text": "exact",
            "audio": {"status": "full", "non_drama": "exact", "drama": "exact"},
            "video": "missing"
          }
        }
      ]
    },
    {
      "plan_id": 401,
      "bible_id": "NTONLY",
      "status": "partial",
      "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30},
      "formats": {
        "text": {
          "status": "partial",
          "items": {"total": 60, "exact": 30, "approximate": 0, "mismatch": 0, "missing": 30}
        },
        "audio": {
          "status": "partial",
          "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30},
          "non_drama": {
            "status": "partial",
            "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30}
          },
          "drama": {
            "status": "partial",
            "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30}
          }
        },
        "video": {
          "status": "incompatible_missing",
          "items": {"total": 60, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 60}
        }
      },
      "playlist_items": [
        {
          "playlist_item_id": 88231,
          "status": "mismatch",
          "formats": {
            "text": "exact",
            "audio": {"status": "incompatible_mismatch", "non_drama": "mismatch", "drama": "mismatch"},
            "video": "missing"
          }
        },
        {
          "playlist_item_id": 88201,
          "status": "incompatible_missing",
          "formats": {
            "text": "missing",
            "audio": {"status": "incompatible_missing", "non_drama": "missing", "drama": "missing"},
            "video": "missing"
          }
        }
      ]
    }
  ],
  "meta": {
    "not_found": {"plan_ids": [], "bible_ids": ["ENGXYZ"]}
  }
}
JSON,
            ],
            'Sample B, Select a Bible for a plan of verse ranges' => [
                <<<'JSON'
{
  "plan_ids": [402],
  "bible_ids": ["ENGESV", "OBTSEC", "DAONLY", "NTONLY"]
}
JSON,
                <<<'JSON'
{
  "data": [
    {
      "plan_id": 402,
      "bible_id": "ENGESV",
      "status": "mismatch",
      "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 10, "missing": 0},
      "formats": {
        "text": {
          "status": "full",
          "items": {"total": 10, "exact": 10, "approximate": 0, "mismatch": 0, "missing": 0}
        },
        "audio": {
          "status": "incompatible_mismatch",
          "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 10, "missing": 0},
          "non_drama": {
            "status": "incompatible_mismatch",
            "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 10, "missing": 0}
          },
          "drama": {
            "status": "incompatible_mismatch",
            "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 10, "missing": 0}
          }
        }
      }
    },
    {
      "plan_id": 402,
      "bible_id": "OBTSEC",
      "status": "mismatch",
      "items": {"total": 10, "exact": 2, "approximate": 0, "mismatch": 8, "missing": 0},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
        },
        "audio": {
          "status": "mismatch",
          "items": {"total": 10, "exact": 2, "approximate": 0, "mismatch": 8, "missing": 0},
          "non_drama": {
            "status": "mismatch",
            "items": {"total": 10, "exact": 2, "approximate": 0, "mismatch": 8, "missing": 0}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
          }
        }
      }
    },
    {
      "plan_id": 402,
      "bible_id": "DAONLY",
      "status": "incompatible_mismatch",
      "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 10, "missing": 0},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
        },
        "audio": {
          "status": "incompatible_mismatch",
          "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 10, "missing": 0},
          "non_drama": {
            "status": "incompatible_mismatch",
            "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 10, "missing": 0}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
          }
        }
      }
    },
    {
      "plan_id": 402,
      "bible_id": "NTONLY",
      "status": "incompatible_missing",
      "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
        },
        "audio": {
          "status": "incompatible_missing",
          "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10},
          "non_drama": {
            "status": "incompatible_missing",
            "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
          }
        }
      }
    }
  ],
  "meta": {
    "not_found": {"plan_ids": [], "bible_ids": []}
  }
}
JSON,
            ],
            'Sample C, whole chapters on section audio' => [
                <<<'JSON'
{
  "plan_ids": [404],
  "bible_ids": ["OBTSEC", "DAONLY"]
}
JSON,
                <<<'JSON'
{
  "data": [
    {
      "plan_id": 404,
      "bible_id": "OBTSEC",
      "status": "approximate",
      "items": {"total": 15, "exact": 5, "approximate": 10, "mismatch": 0, "missing": 0},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 15, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 15}
        },
        "audio": {
          "status": "approximate",
          "items": {"total": 15, "exact": 5, "approximate": 10, "mismatch": 0, "missing": 0},
          "non_drama": {
            "status": "approximate",
            "items": {"total": 15, "exact": 5, "approximate": 10, "mismatch": 0, "missing": 0}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 15, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 15}
          }
        }
      }
    },
    {
      "plan_id": 404,
      "bible_id": "DAONLY",
      "status": "full",
      "items": {"total": 15, "exact": 15, "approximate": 0, "mismatch": 0, "missing": 0},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 15, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 15}
        },
        "audio": {
          "status": "full",
          "items": {"total": 15, "exact": 15, "approximate": 0, "mismatch": 0, "missing": 0},
          "non_drama": {
            "status": "full",
            "items": {"total": 15, "exact": 15, "approximate": 0, "mismatch": 0, "missing": 0}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 15, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 15}
          }
        }
      }
    }
  ],
  "meta": {
    "not_found": {"plan_ids": [], "bible_ids": []}
  }
}
JSON,
            ],
            'Sample D, a plan of passages with a few whole chapters' => [
                <<<'JSON'
{
  "plan_ids": [403],
  "bible_ids": ["ENGESV", "DAONLY"]
}
JSON,
                <<<'JSON'
{
  "data": [
    {
      "plan_id": 403,
      "bible_id": "ENGESV",
      "status": "full",
      "items": {"total": 40, "exact": 40, "approximate": 0, "mismatch": 0, "missing": 0},
      "formats": {
        "text": {
          "status": "full",
          "items": {"total": 40, "exact": 40, "approximate": 0, "mismatch": 0, "missing": 0}
        },
        "audio": {
          "status": "full",
          "items": {"total": 40, "exact": 40, "approximate": 0, "mismatch": 0, "missing": 0},
          "non_drama": {
            "status": "full",
            "items": {"total": 40, "exact": 40, "approximate": 0, "mismatch": 0, "missing": 0}
          },
          "drama": {
            "status": "mismatch",
            "items": {"total": 40, "exact": 4, "approximate": 0, "mismatch": 36, "missing": 0}
          }
        }
      }
    },
    {
      "plan_id": 403,
      "bible_id": "DAONLY",
      "status": "mismatch",
      "items": {"total": 40, "exact": 4, "approximate": 0, "mismatch": 36, "missing": 0},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 40, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 40}
        },
        "audio": {
          "status": "mismatch",
          "items": {"total": 40, "exact": 4, "approximate": 0, "mismatch": 36, "missing": 0},
          "non_drama": {
            "status": "mismatch",
            "items": {"total": 40, "exact": 4, "approximate": 0, "mismatch": 36, "missing": 0}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 40, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 40}
          }
        }
      }
    }
  ],
  "meta": {
    "not_found": {"plan_ids": [], "bible_ids": []}
  }
}
JSON,
            ],
            'Sample E, Android (include_opus)' => [
                <<<'JSON'
{
  "plan_ids": [401],
  "bible_ids": ["OPUSDR"],
  "playlist_item_ids": [88231],
  "include_opus": true
}
JSON,
                <<<'JSON'
{
  "data": [
    {
      "plan_id": 401,
      "bible_id": "OPUSDR",
      "status": "mismatch",
      "items": {"total": 60, "exact": 52, "approximate": 0, "mismatch": 8, "missing": 0},
      "formats": {
        "text": {
          "status": "partial",
          "items": {"total": 60, "exact": 30, "approximate": 0, "mismatch": 0, "missing": 30}
        },
        "audio": {
          "status": "mismatch",
          "items": {"total": 60, "exact": 52, "approximate": 0, "mismatch": 8, "missing": 0},
          "non_drama": {
            "status": "partial",
            "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30}
          },
          "drama": {
            "status": "partial",
            "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30}
          }
        }
      },
      "playlist_items": [
        {
          "playlist_item_id": 88231,
          "status": "mismatch",
          "formats": {
            "text": "exact",
            "audio": {"status": "incompatible_mismatch", "non_drama": "mismatch", "drama": "missing"}
          }
        }
      ]
    }
  ],
  "meta": {
    "not_found": {"plan_ids": [], "bible_ids": []}
  }
}
JSON,
            ],
            'Sample E, iOS (no include_opus)' => [
                <<<'JSON'
{
  "plan_ids": [401],
  "bible_ids": ["OPUSDR"],
  "playlist_item_ids": [88231]
}
JSON,
                <<<'JSON'
{
  "data": [
    {
      "plan_id": 401,
      "bible_id": "OPUSDR",
      "status": "partial",
      "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30},
      "formats": {
        "text": {
          "status": "partial",
          "items": {"total": 60, "exact": 30, "approximate": 0, "mismatch": 0, "missing": 30}
        },
        "audio": {
          "status": "partial",
          "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30},
          "non_drama": {
            "status": "partial",
            "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 60, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 60}
          }
        }
      },
      "playlist_items": [
        {
          "playlist_item_id": 88231,
          "status": "mismatch",
          "formats": {
            "text": "exact",
            "audio": {"status": "incompatible_mismatch", "non_drama": "mismatch", "drama": "missing"}
          }
        }
      ]
    }
  ],
  "meta": {
    "not_found": {"plan_ids": [], "bible_ids": []}
  }
}
JSON,
            ],
            'Sample F, video as the apps send it today' => [
                <<<'JSON'
{
  "plan_ids": [403],
  "bible_ids": ["MATVID"],
  "playlist_item_ids": [90101],
  "include_video": true
}
JSON,
                <<<'JSON'
{
  "data": [
    {
      "plan_id": 403,
      "bible_id": "MATVID",
      "status": "mismatch",
      "items": {"total": 40, "exact": 4, "approximate": 0, "mismatch": 36, "missing": 0},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 40, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 40}
        },
        "audio": {
          "status": "mismatch",
          "items": {"total": 40, "exact": 4, "approximate": 0, "mismatch": 36, "missing": 0},
          "non_drama": {
            "status": "mismatch",
            "items": {"total": 40, "exact": 4, "approximate": 0, "mismatch": 36, "missing": 0}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 40, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 40}
          }
        },
        "video": {
          "status": "approximate",
          "items": {"total": 40, "exact": 0, "approximate": 40, "mismatch": 0, "missing": 0}
        }
      },
      "playlist_items": [
        {
          "playlist_item_id": 90101,
          "status": "incompatible_mismatch",
          "formats": {
            "text": "missing",
            "audio": {"status": "incompatible_mismatch", "non_drama": "mismatch", "drama": "missing"},
            "video": "approximate"
          }
        }
      ]
    }
  ],
  "meta": {
    "not_found": {"plan_ids": [], "bible_ids": []}
  }
}
JSON,
            ],
            'Sample F, allow_video_only' => [
                <<<'JSON'
{
  "plan_ids": [403],
  "bible_ids": ["MATVID"],
  "playlist_item_ids": [90101],
  "include_video": true,
  "allow_video_only": true
}
JSON,
                <<<'JSON'
{
  "data": [
    {
      "plan_id": 403,
      "bible_id": "MATVID",
      "status": "approximate",
      "items": {"total": 40, "exact": 4, "approximate": 36, "mismatch": 0, "missing": 0},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 40, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 40}
        },
        "audio": {
          "status": "mismatch",
          "items": {"total": 40, "exact": 4, "approximate": 0, "mismatch": 36, "missing": 0},
          "non_drama": {
            "status": "mismatch",
            "items": {"total": 40, "exact": 4, "approximate": 0, "mismatch": 36, "missing": 0}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 40, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 40}
          }
        },
        "video": {
          "status": "approximate",
          "items": {"total": 40, "exact": 0, "approximate": 40, "mismatch": 0, "missing": 0}
        }
      },
      "playlist_items": [
        {
          "playlist_item_id": 90101,
          "status": "approximate",
          "formats": {
            "text": "missing",
            "audio": {"status": "incompatible_mismatch", "non_drama": "mismatch", "drama": "missing"},
            "video": "approximate"
          }
        }
      ]
    }
  ],
  "meta": {
    "not_found": {"plan_ids": [], "bible_ids": []}
  }
}
JSON,
            ],
            'Sample G, exact_threshold' => [
                <<<'JSON'
{
  "plan_ids": [401],
  "bible_ids": ["ENGESV", "NTONLY", "DAONLY"],
  "exact_threshold": 80
}
JSON,
                <<<'JSON'
{
  "data": [
    {
      "plan_id": 401,
      "bible_id": "ENGESV",
      "status": "full",
      "items": {"total": 60, "exact": 60, "approximate": 0, "mismatch": 0, "missing": 0},
      "formats": {
        "text": {
          "status": "full",
          "items": {"total": 60, "exact": 60, "approximate": 0, "mismatch": 0, "missing": 0}
        },
        "audio": {
          "status": "mismatch",
          "items": {"total": 60, "exact": 56, "approximate": 0, "mismatch": 4, "missing": 0},
          "non_drama": {
            "status": "mismatch",
            "items": {"total": 60, "exact": 56, "approximate": 0, "mismatch": 4, "missing": 0}
          },
          "drama": {
            "status": "mismatch",
            "items": {"total": 60, "exact": 52, "approximate": 0, "mismatch": 8, "missing": 0}
          }
        }
      }
    },
    {
      "plan_id": 401,
      "bible_id": "NTONLY",
      "status": "partial",
      "items": {"total": 60, "exact": 30, "approximate": 0, "mismatch": 0, "missing": 30},
      "formats": {
        "text": {
          "status": "partial",
          "items": {"total": 60, "exact": 30, "approximate": 0, "mismatch": 0, "missing": 30}
        },
        "audio": {
          "status": "partial",
          "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30},
          "non_drama": {
            "status": "partial",
            "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30}
          },
          "drama": {
            "status": "partial",
            "items": {"total": 60, "exact": 26, "approximate": 0, "mismatch": 4, "missing": 30}
          }
        }
      }
    },
    {
      "plan_id": 401,
      "bible_id": "DAONLY",
      "status": "mismatch",
      "items": {"total": 60, "exact": 52, "approximate": 0, "mismatch": 8, "missing": 0},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 60, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 60}
        },
        "audio": {
          "status": "mismatch",
          "items": {"total": 60, "exact": 52, "approximate": 0, "mismatch": 8, "missing": 0},
          "non_drama": {
            "status": "mismatch",
            "items": {"total": 60, "exact": 52, "approximate": 0, "mismatch": 8, "missing": 0}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 60, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 60}
          }
        }
      }
    }
  ],
  "meta": {
    "not_found": {"plan_ids": [], "bible_ids": []}
  }
}
JSON,
            ],
            'Sample H, allow_chapter_audio' => [
                <<<'JSON'
{
  "plan_ids": [402],
  "bible_ids": ["ENGESV", "OBTSEC", "DAONLY", "NTONLY"],
  "allow_chapter_audio": true
}
JSON,
                <<<'JSON'
{
  "data": [
    {
      "plan_id": 402,
      "bible_id": "ENGESV",
      "status": "approximate",
      "items": {"total": 10, "exact": 0, "approximate": 10, "mismatch": 0, "missing": 0},
      "formats": {
        "text": {
          "status": "full",
          "items": {"total": 10, "exact": 10, "approximate": 0, "mismatch": 0, "missing": 0}
        },
        "audio": {
          "status": "approximate",
          "items": {"total": 10, "exact": 0, "approximate": 10, "mismatch": 0, "missing": 0},
          "non_drama": {
            "status": "approximate",
            "items": {"total": 10, "exact": 0, "approximate": 10, "mismatch": 0, "missing": 0}
          },
          "drama": {
            "status": "approximate",
            "items": {"total": 10, "exact": 0, "approximate": 10, "mismatch": 0, "missing": 0}
          }
        }
      }
    },
    {
      "plan_id": 402,
      "bible_id": "OBTSEC",
      "status": "approximate",
      "items": {"total": 10, "exact": 2, "approximate": 8, "mismatch": 0, "missing": 0},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
        },
        "audio": {
          "status": "approximate",
          "items": {"total": 10, "exact": 2, "approximate": 8, "mismatch": 0, "missing": 0},
          "non_drama": {
            "status": "approximate",
            "items": {"total": 10, "exact": 2, "approximate": 8, "mismatch": 0, "missing": 0}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
          }
        }
      }
    },
    {
      "plan_id": 402,
      "bible_id": "DAONLY",
      "status": "approximate",
      "items": {"total": 10, "exact": 0, "approximate": 10, "mismatch": 0, "missing": 0},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
        },
        "audio": {
          "status": "approximate",
          "items": {"total": 10, "exact": 0, "approximate": 10, "mismatch": 0, "missing": 0},
          "non_drama": {
            "status": "approximate",
            "items": {"total": 10, "exact": 0, "approximate": 10, "mismatch": 0, "missing": 0}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
          }
        }
      }
    },
    {
      "plan_id": 402,
      "bible_id": "NTONLY",
      "status": "incompatible_missing",
      "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10},
      "formats": {
        "text": {
          "status": "incompatible_missing",
          "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
        },
        "audio": {
          "status": "incompatible_missing",
          "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10},
          "non_drama": {
            "status": "incompatible_missing",
            "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
          },
          "drama": {
            "status": "incompatible_missing",
            "items": {"total": 10, "exact": 0, "approximate": 0, "mismatch": 0, "missing": 10}
          }
        }
      }
    }
  ],
  "meta": {
    "not_found": {"plan_ids": [], "bible_ids": []}
  }
}
JSON,
            ],
        ];
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Audio as a whole: Sam's two examples (design section 12)

    public function testAudioAsAWholeIsThePerItemUnionNotTheBetterTotal()
    {
        // Drama exact on items 1-3 of 5, non-drama on items 2-4.
        $items = [];
        foreach (range(1, 5) as $chapter) {
            $items[] = $this->item('MRK', $chapter);
        }
        $filesets = [
            self::fileset('XN2DA', 'audio_drama', ['MRK' => [[1, 1, 1, null], [2, 1, 2, null], [3, 1, 3, null]]]),
            self::fileset('XN1DA', 'audio', ['MRK' => [[2, 1, 2, null], [3, 1, 3, null], [4, 1, 4, null]]]),
        ];

        $audio = $this->evaluate($items, $filesets)['formats']['audio'];

        $this->assertSame(self::counts(5, 3, 0, 0, 2), $audio['drama']['items']);
        $this->assertSame(self::counts(5, 3, 0, 0, 2), $audio['non_drama']['items']);
        $this->assertSame(self::counts(5, 4, 0, 0, 1), $audio['items']);
        $this->assertSame('partial', $audio['status']);
    }

    public function testOldTestamentDramaAndNewTestamentNonDramaAreFullAsAWhole()
    {
        // Bible in a Year in miniature: 100 OT and 80 NT items, all whole chapters.
        $ot = ['GEN' => [1, 50], 'EXO' => [1, 40], 'LEV' => [1, 10]];
        $nt = ['MAT' => [1, 28], 'MRK' => [1, 16], 'LUK' => [1, 24], 'JHN' => [1, 12]];
        $items = [];
        foreach (self::chapters($ot + $nt) as $book => $chapters) {
            foreach ($chapters as $chapter) {
                $items[] = $this->item($book, $chapter);
            }
        }
        $this->assertCount(180, $items);
        $filesets = [
            self::fileset('XO2DA', 'audio_drama', self::chapterPieces($ot)),
            self::fileset('XN1DA', 'audio', self::chapterPieces($nt)),
        ];

        $result = $this->evaluate($items, $filesets);
        $audio = $result['formats']['audio'];

        $this->assertSame('partial', $audio['drama']['status']);
        $this->assertSame(self::counts(180, 100, 0, 0, 80), $audio['drama']['items']);
        $this->assertSame('partial', $audio['non_drama']['status']);
        $this->assertSame(self::counts(180, 80, 0, 0, 100), $audio['non_drama']['items']);
        $this->assertSame('full', $audio['status']);
        $this->assertSame(self::counts(180, 180, 0, 0, 0), $audio['items']);
        $this->assertSame('full', $result['status']);
    }

    public function testDramaExactAndNonDramaMismatchIsExactForAudioAsAWhole()
    {
        $items = [$this->item('MAT', 5, 1, 12, 1)];
        $filesets = [
            self::fileset('XN2SA', 'audio_drama_stream', self::chapterPieces(self::NT)),
            self::fileset('XN1DA', 'audio', self::chapterPieces(self::NT)),
        ];

        $item = $this->evaluate($items, $filesets, [], 1)['item'];

        $this->assertSame(['status' => 'full', 'non_drama' => 'mismatch', 'drama' => 'exact'], $item['formats']['audio']);
        $this->assertSame('full', $item['status']);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Single rules: one item against one fileset

    /** items counts in their key order. */
    private static function counts(int $total, int $exact, int $approximate, int $mismatch, int $missing) : array
    {
        return [
            'total'       => $total,
            'exact'       => $exact,
            'approximate' => $approximate,
            'mismatch'    => $mismatch,
            'missing'     => $missing,
        ];
    }

    /** The answer word for one item in one format. */
    private function word(array $item, array $filesets, string $format, array $options = []) : string
    {
        $item['id'] = 1;
        $formats = $this->evaluate([$item], $filesets, $options, 1)['item']['formats'];

        return $format === 'non_drama' || $format === 'drama' ? $formats['audio'][$format] : $formats[$format];
    }

    /**
     * The calculator's answer. With $playlist_item_id, 'item' is that item's block from playlist_items, as the single
     * item the rule tests look at.
     */
    private function evaluate(array $items, array $filesets, array $options = [], ?int $playlist_item_id = null) : array
    {
        $calculator = new PlanCompatibilityCalculator(
            $options['include_video'] ?? false,
            $options['include_opus'] ?? false,
            $options['allow_video_only'] ?? false,
            $options['exact_threshold'] ?? 0,
            $options['allow_chapter_audio'] ?? false
        );

        $result = $calculator->evaluate(
            $items,
            $calculator->prepareBible($filesets),
            $playlist_item_id === null ? null : [$playlist_item_id]
        );
        if ($playlist_item_id !== null) {
            $this->assertCount(1, $result['playlist_items']);
            $result['item'] = $result['playlist_items'][0];
        }

        return $result;
    }

    private static function text(array $books = self::NT) : array
    {
        return self::fileset('XN_ET', 'text_plain', self::textPieces($books));
    }

    private static function chapterFiles(array $pieces, string $type = 'audio') : array
    {
        return self::fileset('XN1DA', $type, $pieces);
    }

    private static function sections(array $pieces, string $type = 'audio') : array
    {
        return self::fileset('XN1DA', $type, $pieces, 'section');
    }

    private static function clips(array $pieces) : array
    {
        return self::fileset('XP2DV', 'video_stream', $pieces, 'section');
    }

    public function testTextIsExactApproximateWhenPartlyThereOrMissing()
    {
        $this->assertSame('exact', $this->word($this->item('MAT', 5, 3, 10), [self::text()], 'text'));
        $this->assertSame('exact', $this->word($this->item('MAT', 5), [self::text()], 'text'));
        $this->assertSame('missing', $this->word($this->item('MRK', 1), [self::text()], 'text'));
        // Revision 4: partly there is approximate, and text is never mismatch. MAT has no chapter 29.
        $this->assertSame('approximate', $this->word($this->item('MAT', 28, null, null, null, 29), [self::text()], 'text'));
        $this->assertSame('approximate', $this->word($this->item('MAT', 28, 10, 5, null, 29), [self::text()], 'text'));
        // Text whose MAT 1 starts at verse 17 (the chapter's last verse is still 25).
        $late = [self::fileset('XN_ET', 'text_plain', ['MAT' => [[1, 17, 1, 25]]])];
        $this->assertSame('approximate', $this->word($this->item('MAT', 1), $late, 'text'));
        $this->assertSame('approximate', $this->word($this->item('MAT', 1, 1, 20), $late, 'text'));
        $this->assertSame('missing', $this->word($this->item('MAT', 1, 1, 16), $late, 'text'));
    }

    public function testTextFormatFromFilesIsCutAnywhere()
    {
        $text_format = self::fileset('XN_ET', 'text_format', self::chapterPieces(self::NT));

        $this->assertSame('exact', $this->word($this->item('MAT', 5, 3, 10), [$text_format], 'text'));
    }

    public function testUsxAndJsonCountAsText()
    {
        // Revision 5, R19, with rows shaped as on dev (PlanCompatibilityInputTest::testPiecesFromFileRows): text_usx is
        // one row per book, from its first chapter to its last; text_json one row per chapter. Neither has verse numbers.
        $usx = self::fileset('XO_ET-usx', 'text_usx', ['GEN' => [[1, 1, 50, null]]]);
        $this->assertSame('exact', $this->word($this->item('GEN', 1, 3, 5), [$usx], 'text'));
        $this->assertSame('exact', $this->word($this->item('GEN', 50), [$usx], 'text'), 'the book row covers every chapter');
        $this->assertSame('exact', $this->word($this->item('GEN', 24, 10, 20, null, 25), [$usx], 'text'));
        $this->assertSame('missing', $this->word($this->item('EXO', 1), [$usx], 'text'));

        $json = self::fileset('XO_ET-json', 'text_json', ['GEN' => [[1, 1, 1, null], [2, 1, 2, null]]]);
        $this->assertSame('exact', $this->word($this->item('GEN', 2), [$json], 'text'));
        $this->assertSame('exact', $this->word($this->item('GEN', 1, 3, 5), [$json], 'text'));
        $this->assertSame('approximate', $this->word($this->item('GEN', 2, null, null, null, 3), [$json], 'text'), 'GEN 3 is not there');
        $this->assertSame('missing', $this->word($this->item('GEN', 3), [$json], 'text'));

        // A Bible whose only text is USX (5 on prod) is full on a plan of its books, where it was incompatible_missing.
        $plan = [$this->item('GEN', 1), $this->item('GEN', 2, 4, 10), $this->item('GEN', 50)];
        $result = $this->evaluate($plan, [$usx]);
        $this->assertSame(['status' => 'full', 'items' => self::counts(3, 3, 0, 0, 0)], $result['formats']['text']);
        $this->assertSame('full', $result['status']);
    }

    public function testUsxAndJsonGiveNoLastVerseAndChangeNothingBesidePlainText()
    {
        // USX has no verse numbers, so in a Bible whose only text is USX, LUK 1's last verse (80) comes from the
        // sections' numbered ends, as in a Bible with no text: the end of LUK 1:5-90 is clamped to it.
        $usx = self::fileset('XN_ET-usx', 'text_usx', ['LUK' => [[1, 1, 24, null]]]);
        $sections = self::sections(['LUK' => [[1, 1, 1, 4], [1, 5, 1, 80]]]);
        $this->assertSame('exact', $this->word($this->item('LUK', 1, 5, 90), [$usx, $sections], 'non_drama'));
        $this->assertSame('exact', $this->word($this->item('LUK', 1, 5, 90), [$usx, $sections], 'text'));
        $this->assertSame('exact', $this->word($this->item('LUK', 1, 5, 90), [$sections], 'non_drama'), 'as without the USX');

        // Beside plain text covering the same books, they change no answer.
        $usx_nt = self::fileset('XN_ET-usx', 'text_usx', ['MAT' => [[1, 1, 28, null]]]);
        $json_nt = self::fileset('XN_ET-json', 'text_json', self::chapterPieces(self::NT));
        $files = self::chapterFiles(self::chapterPieces(self::NT));
        $plan = array_merge($this->quarterExactPlan(), [$this->item('MAT', 5, 40, 60)]);
        $this->assertSame(
            $this->evaluate($plan, [self::text(), $files]),
            $this->evaluate($plan, [$usx_nt, self::text(), $json_nt, $files])
        );
    }

    public function testACombinedVerseNeverMakesTextOrAStreamInexact()
    {
        // R21, on purpose (Sam, 2026-11-12): ENGCEV's plain text joins MAT 1:2-11 into one verse, and 1:12-16. Its
        // bible_verses rows for MAT 1 come to one piece (PlanCompatibilityService::verseRows takes the lowest verse and
        // the highest verse end), so the engine never sees inside the combined verse.
        $text = self::fileset('XN_ET', 'text_plain', PlanCompatibilityService::piecesFromVerseRows([
            ['book_id' => 'MAT', 'chapter' => 1, 'verse_min' => 1, 'verse_max' => 25],
        ]));
        $this->assertSame('exact', $this->word($this->item('MAT', 1, 1, 6), [$text], 'text'), 'it ends inside 1:2-11');
        $this->assertSame('exact', $this->word($this->item('MAT', 1, 5, 20), [$text], 'text'), 'it starts inside 1:2-11');
        $stream = self::fileset('XN1SA', 'audio_stream', self::chapterPieces(self::NT));
        $this->assertSame('exact', $this->word($this->item('MAT', 1, 1, 6), [$stream], 'non_drama'));
    }

    public function testStreamIsCutAnywhere()
    {
        $stream = self::fileset('XN1SA', 'audio_stream', self::chapterPieces(self::NT));

        $this->assertSame('exact', $this->word($this->item('MAT', 5, 3, 10), [$stream], 'non_drama'));
        $this->assertSame('exact', $this->word($this->item('MAT', 5, 3, 10, null, 6), [$stream], 'non_drama'));
    }

    public function testChapterFilesAreExactForWholeChaptersAndMismatchForVerseRanges()
    {
        $files = [self::chapterFiles(self::chapterPieces(self::NT)), self::text()];

        $this->assertSame('exact', $this->word($this->item('MAT', 5), $files, 'non_drama'));
        $this->assertSame('exact', $this->word($this->item('MAT', 1, null, null, null, 3), $files, 'non_drama'));
        $this->assertSame('exact', $this->word($this->item('MAT', 5, 1, 48), $files, 'non_drama'), 'verses 1 to the last verse are the whole chapter');
        $this->assertSame('mismatch', $this->word($this->item('MAT', 5, 3, 10), $files, 'non_drama'));
        $this->assertSame('mismatch', $this->word($this->item('MAT', 1, 18, 10, null, 2), $files, 'non_drama'));
    }

    public function testSectionsForWholeChaptersAreExactOrApproximate()
    {
        $sections = [self::sections(['LUK' => [
            [1, 1, 1, 4], [1, 5, 1, 25], [1, 26, 1, 80],
            [2, 1, 2, 52],
            [3, 1, 4, 5], [4, 6, 4, 44],
        ]])];

        $this->assertSame('exact', $this->word($this->item('LUK', 2), $sections, 'non_drama'), 'one section is the chapter');
        $this->assertSame('approximate', $this->word($this->item('LUK', 1), $sections, 'non_drama'), 'several sections');
        $this->assertSame('approximate', $this->word($this->item('LUK', 3), $sections, 'non_drama'), 'a section running on into chapter 4');
        $this->assertSame('approximate', $this->word($this->item('LUK', 3, null, null, null, 4), $sections, 'non_drama'), 'two sections for two whole chapters');
        $this->assertSame('missing', $this->word($this->item('LUK', 5), $sections, 'non_drama'));
    }

    public function testSectionsForVerseRangesAreExactOnlyWhenOneSectionMatches()
    {
        $sections = [self::sections(['LUK' => [[1, 1, 1, 4], [1, 5, 1, 25], [1, 26, 1, 38], [1, 39, 1, 56], [1, 57, 1, 80]]])];

        $this->assertSame('exact', $this->word($this->item('LUK', 1, 5, 25), $sections, 'non_drama'));
        $this->assertSame('exact', $this->word($this->item('LUK', 1, 57, 80), $sections, 'non_drama'));
        $this->assertSame('mismatch', $this->word($this->item('LUK', 1, 5, 20), $sections, 'non_drama'), 'one section running past it');
        $this->assertSame('mismatch', $this->word($this->item('LUK', 1, 1, 25), $sections, 'non_drama'), 'two sections lining up with its first and last verse');
    }

    public function testASectionSpanningTwoChapters()
    {
        $sections = [self::sections(['LUK' => [
            [7, 1, 7, 10], [7, 11, 7, 35], [7, 36, 8, 3], [8, 4, 8, 15], [8, 16, 8, 56],
        ]])];

        $this->assertSame('exact', $this->word($this->item('LUK', 7, 36, 3, null, 8), $sections, 'non_drama'));
        $this->assertSame('mismatch', $this->word($this->item('LUK', 7, 36, 50), $sections, 'non_drama'), 'a verse range; the section runs on into chapter 8');
        $this->assertSame('approximate', $this->word($this->item('LUK', 8), $sections, 'non_drama'), 'verses 1-3 come from a section that starts in chapter 7');
        $this->assertSame('approximate', $this->word($this->item('LUK', 7, null, null, null, 8), $sections, 'non_drama'));
    }

    public function testARecordingThatStartsLateIsPartlyThere()
    {
        // The recording starts at MAT 1:17 (revision 4: only an item none of whose verses is there is missing).
        $files = [self::chapterFiles(['MAT' => [[1, 17, 1, null]]]), self::text()];

        $this->assertSame('approximate', $this->word($this->item('MAT', 1), $files, 'non_drama'), 'a whole chapter, partly there');
        $this->assertSame('mismatch', $this->word($this->item('MAT', 1, 1, 20), $files, 'non_drama'), 'a verse range, partly there');
        $this->assertSame('missing', $this->word($this->item('MAT', 1, 1, 16), $files, 'non_drama'), 'none of it is there');
        $this->assertSame('mismatch', $this->word($this->item('MAT', 1, 18, 25), $files, 'non_drama'));

        // On a stream, the part that is there plays: approximate, whatever the item's edges.
        $stream = [self::fileset('XN1SA', 'audio_stream', ['MAT' => [[1, 17, 1, null]]]), self::text()];
        $this->assertSame('approximate', $this->word($this->item('MAT', 1), $stream, 'non_drama'));
        $this->assertSame('approximate', $this->word($this->item('MAT', 1, 1, 20), $stream, 'non_drama'));
        $this->assertSame('exact', $this->word($this->item('MAT', 1, 18, 20), $stream, 'non_drama'));
        $this->assertSame('missing', $this->word($this->item('MAT', 1, 1, 16), $stream, 'non_drama'));
    }

    public function testAMultiChapterItemWithAChapterAbsent()
    {
        // MAT 2 is absent from the recording.
        $files = [self::chapterFiles(['MAT' => [[1, 1, 1, null], [3, 1, 3, null]]]), self::text()];

        $this->assertSame('exact', $this->word($this->item('MAT', 1, null, null, null, 1), $files, 'non_drama'));
        $this->assertSame('approximate', $this->word($this->item('MAT', 1, null, null, null, 3), $files, 'non_drama'), 'whole chapters');
        $this->assertSame('mismatch', $this->word($this->item('MAT', 1, 5, 10, null, 3), $files, 'non_drama'), 'it starts and ends inside a chapter');
        $this->assertSame('missing', $this->word($this->item('MAT', 2), $files, 'non_drama'));
    }

    public function testAFirstChapterPartPastTheLastVerseIsLeftOut()
    {
        // GEN has 31 verses a chapter in the fixtures, so GEN 1:32-3 is GEN 2-3: whole chapters, on every kind of
        // fileset alike (review L4-1).
        $item = $this->item('GEN', 1, 32, null, null, 3);
        $text = self::text(self::OT);
        $files = [self::chapterFiles(self::chapterPieces(self::OT)), $text];
        $sections = [self::sections(['GEN' => [[1, 1, 1, 31], [2, 1, 2, 15], [2, 16, 2, 31], [3, 1, 3, 15], [3, 16, 3, 31]]]), $text];

        $this->assertSame('exact', $this->word($item, $files, 'non_drama'));
        $this->assertSame('approximate', $this->word($item, $sections, 'non_drama'));
        $this->assertSame('approximate', $this->word($this->item('GEN', 2, null, null, null, 3), $sections, 'non_drama'));
        $this->assertSame('missing', $this->word($this->item('GEN', 1, 32, 40), $files, 'non_drama'), 'no verse of it in this Bible');
    }

    public function testTheEndIsClampedToTheChaptersLastVerse()
    {
        $files = [self::chapterFiles(self::chapterPieces(self::NT)), self::text()];

        // MAT 5 has 48 verses in this Bible.
        $this->assertSame('exact', $this->word($this->item('MAT', 5, 40, 60), $files, 'text'));
        $this->assertSame('exact', $this->word($this->item('MAT', 5, 1, 60), $files, 'non_drama'), 'clamped, it is the whole chapter');
        $this->assertSame('missing', $this->word($this->item('MAT', 5, 50, 55), $files, 'text'), 'nothing of the item is in this chapter');
        $this->assertSame('missing', $this->word($this->item('MAT', 5, 50, 55), $files, 'non_drama'));
    }

    public function testWithoutTextTheLastVerseComesFromThePieces()
    {
        $sections = [self::sections(['LUK' => [[1, 1, 1, 4], [1, 5, 1, 80]]])];

        $this->assertSame('exact', $this->word($this->item('LUK', 1, 5, 90), $sections, 'non_drama'));
        $this->assertSame('exact', $this->word($this->item('LUK', 1, 5), $sections, 'non_drama'));
    }

    public function testGapsInsideAChapterDontCount()
    {
        // Verse 11 is not numbered in this translation; the chapter's last verse is 20.
        $sections = [self::sections(['LUK' => [[1, 1, 1, 10], [1, 12, 1, 20]]])];

        $this->assertSame('approximate', $this->word($this->item('LUK', 1, 1, 20), $sections, 'non_drama'), 'the whole chapter, in two sections');
        $this->assertSame('mismatch', $this->word($this->item('LUK', 1, 1, 15), $sections, 'non_drama'), 'covered, not missing');
    }

    public function testAnItemStartingOrEndingOnAVerseTheTranslationLeavesOut()
    {
        // MRK 11:26 is not in this translation; the sections around it are 11:20-25 and 11:27-33.
        $sections = [self::sections(['MRK' => [[11, 1, 11, 19], [11, 20, 11, 25], [11, 27, 11, 33]]])];

        $this->assertSame('exact', $this->word($this->item('MRK', 11, 20, 26), $sections, 'non_drama'));
        $this->assertSame('exact', $this->word($this->item('MRK', 11, 26, 33), $sections, 'non_drama'));
        $this->assertSame('missing', $this->word($this->item('MRK', 11, 26, 26), $sections, 'non_drama'), 'nothing of it is in this Bible');
        $this->assertSame('mismatch', $this->word($this->item('MRK', 11, 24, 28), $sections, 'non_drama'));
        // A start before every piece, or an end after every piece, is not a gap in numbering: the item is only partly
        // there, so this verse range is mismatch on sections (missing before revision 4).
        $late = [self::sections(['MRK' => [[11, 3, 11, 25], [11, 27, 11, 33]]])];
        $this->assertSame('mismatch', $this->word($this->item('MRK', 11, 1, 25), $late, 'non_drama'));
        // The text says MRK 11 has 31 verses (the fixture count); this recording stops at 30.
        $early = [self::sections(['MRK' => [[11, 1, 11, 25], [11, 27, 11, 30]]]), self::text(['MRK' => [11, 11]])];
        $this->assertSame('mismatch', $this->word($this->item('MRK', 11, 27, 31), $early, 'non_drama'));
        $this->assertSame('approximate', $this->word($this->item('MRK', 11), $early, 'non_drama'), 'a whole chapter, partly there');
    }

    public function testDuplicatePiecesAreOnePiece()
    {
        $clips = self::clips(['MAT' => [[1, 1, 1, 17], [1, 1, 1, 17], [1, 18, 1, 25]]]);

        $this->assertSame('exact', $this->word($this->item('MAT', 1, 1, 17), [$clips], 'video', ['include_video' => true]));
    }

    public function testItemVersesAsStored()
    {
        $files = [self::chapterFiles(self::chapterPieces(self::NT)), self::text()];

        // An empty or 0 verse is no verse, as PlaylistItems::getFullChapterAttribute reads it; "2b" is verse 2.
        $this->assertSame('exact', $this->word($this->item('MAT', 1, '0', null), $files, 'non_drama'));
        $this->assertSame('mismatch', $this->word($this->item('MAT', 1, '2b', '10'), $files, 'non_drama'));
        $this->assertSame('mismatch', $this->word($this->item('MAT', 1, '3', null), $files, 'non_drama'), 'from verse 3 to the end');
        $this->assertSame('exact', $this->word($this->item('MAT', 1, '3', null), $files, 'text'));
        $this->assertSame(['book_id' => 'MAT', 'chapter_start' => 5, 'chapter_end' => 5, 'verse_start' => null, 'verse_end' => 2], PlanCompatibilityCalculator::parseItem(['book_id' => 'MAT', 'chapter_start' => 5, 'chapter_end' => 3, 'verse_start' => '', 'verse_end' => '02']));
        $this->assertSame(5, PlanCompatibilityCalculator::parseItem(['book_id' => 'MAT', 'chapter_start' => '5', 'chapter_end' => null, 'verse_start' => null, 'verse_end' => null])['chapter_end']);
    }

    public function testVideoThatCoversAnItemInexactlyIsApproximateWhateverItsEdges()
    {
        $clips = [self::clips(['MAT' => [[1, 1, 1, 17], [1, 18, 1, 25]]])];
        $video = ['include_video' => true];

        $this->assertSame('exact', $this->word($this->item('MAT', 1, 1, 17), $clips, 'video', $video));
        $this->assertSame('approximate', $this->word($this->item('MAT', 1), $clips, 'video', $video), 'two clips');
        $this->assertSame('approximate', $this->word($this->item('MAT', 1, 3, 10), $clips, 'video', $video), 'inside one clip');
        $this->assertSame('approximate', $this->word($this->item('MAT', 1, 10, 20), $clips, 'video', $video), 'across two clips');
        $this->assertSame('missing', $this->word($this->item('MAT', 2), $clips, 'video', $video));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The options

    /** MAT 1, three MAT verse ranges and MRK 1: on NT chapter files, 1 exact, 3 mismatch and 1 missing. */
    private function quarterExactPlan() : array
    {
        return [
            $this->item('MAT', 1),
            $this->item('MAT', 2, 1, 10),
            $this->item('MAT', 3, 1, 10),
            $this->item('MAT', 4, 1, 10),
            $this->item('MRK', 1),
        ];
    }

    /** 4 whole chapters and MAT 5:3-10 (id 5): on NT chapter files, 4 exact and 1 mismatch, 80%. */
    private function fourFifthsExactPlan() : array
    {
        return [
            $this->item('MAT', 1),
            $this->item('MAT', 2),
            $this->item('MAT', 3),
            $this->item('MAT', 4),
            $this->item('MAT', 5, 3, 10, 5),
        ];
    }

    /** The Bible's status and items. */
    private static function bibleAnswer(array $result) : array
    {
        return array_intersect_key($result, ['status' => 1, 'items' => 1]);
    }

    public function testExactThresholdAtTheBoundary()
    {
        // Complete text; the audio as a whole is 4 exact and 1 mismatch, 80%.
        $filesets = [self::text(), self::chapterFiles(self::chapterPieces(self::NT))];
        $plan = $this->fourFifthsExactPlan();
        $bible = function (int $threshold) use ($plan, $filesets) {
            return self::bibleAnswer($this->evaluate($plan, $filesets, ['exact_threshold' => $threshold]));
        };
        $full = ['status' => 'full', 'items' => self::counts(5, 5, 0, 0, 0)];
        $warned = ['status' => 'mismatch', 'items' => self::counts(5, 4, 0, 1, 0)];

        $this->assertSame($warned, $bible(0), 'off: the audio outranks the text');
        $this->assertSame($full, $bible(1));
        $this->assertSame($full, $bible(79));
        $this->assertSame($full, $bible(80), 'at the threshold: close enough, and the text covers the mismatch item');
        $this->assertSame($warned, $bible(81));
        $this->assertSame($warned, $bible(100));
    }

    public function testExactThresholdChangesOnlyTheBiblesStatusAndItems()
    {
        $filesets = [self::text(), self::chapterFiles(self::chapterPieces(self::NT))];
        $plan = $this->fourFifthsExactPlan();

        $without = $this->evaluate($plan, $filesets, [], 5);
        $with = $this->evaluate($plan, $filesets, ['exact_threshold' => 80], 5);

        $this->assertSame('mismatch', $without['status']);
        $this->assertSame('full', $with['status']);
        $this->assertSame($without['formats'], $with['formats'], 'every format, and so every fileset answer, unchanged');
        $this->assertSame($without['item'], $with['item'], 'the requested item unchanged');
        $this->assertSame('mismatch', $with['item']['status'], 'the player still warns for this passage');
        $this->assertSame('mismatch', $with['item']['formats']['audio']['non_drama']);
    }

    public function testExactThresholdCountsApproximateLikeMismatch()
    {
        // LUK 1 in two sections (approximate), LUK 2 to 5 one section each (exact): 4 of 5, 80%.
        $sections = self::sections(['LUK' => [[1, 1, 1, 4], [1, 5, 1, 80], [2, 1, 2, 52], [3, 1, 3, 38], [4, 1, 4, 44], [5, 1, 5, 39]]]);
        $filesets = [self::text(['LUK' => [1, 24]]), $sections];
        $plan = [];
        foreach (range(1, 5) as $chapter) {
            $plan[] = $this->item('LUK', $chapter);
        }

        $this->assertSame('approximate', $this->evaluate($plan, $filesets)['status'], 'approximate audio outranks the text');
        $this->assertSame('full', $this->evaluate($plan, $filesets, ['exact_threshold' => 80])['status']);
        $this->assertSame('approximate', $this->evaluate($plan, $filesets, ['exact_threshold' => 81])['status']);

        // So the share reads the same with allow_chapter_audio, where the mismatch item is approximate.
        $files = [self::text(), self::chapterFiles(self::chapterPieces(self::NT))];
        $chapter_audio = ['allow_chapter_audio' => true];
        $this->assertSame('full', $this->evaluate($this->fourFifthsExactPlan(), $files, $chapter_audio + ['exact_threshold' => 80])['status']);
        $this->assertSame(
            ['status' => 'approximate', 'items' => self::counts(5, 4, 1, 0, 0)],
            self::bibleAnswer($this->evaluate($this->fourFifthsExactPlan(), $files, $chapter_audio + ['exact_threshold' => 81]))
        );
    }

    public function testExactThresholdDoesNotCountMissingAudio()
    {
        // Complete text, audio for the NT only: GEN 1-10 have no audio and don't lower the share, still 4 of 5.
        $filesets = [self::text(self::OT + self::NT), self::chapterFiles(self::chapterPieces(self::NT))];
        $plan = $this->fourFifthsExactPlan();
        foreach (range(1, 10) as $chapter) {
            $plan[] = $this->item('GEN', $chapter);
        }

        $at_80 = $this->evaluate($plan, $filesets, ['exact_threshold' => 80]);
        $this->assertSame(self::counts(15, 4, 0, 1, 10), $at_80['formats']['audio']['items']);
        $this->assertSame(['status' => 'full', 'items' => self::counts(15, 15, 0, 0, 0)], self::bibleAnswer($at_80));
        $this->assertSame(
            ['status' => 'mismatch', 'items' => self::counts(15, 14, 0, 1, 0)],
            self::bibleAnswer($this->evaluate($plan, $filesets, ['exact_threshold' => 81]))
        );
    }

    public function testExactThresholdWithoutTextKeepsTheAudiosAnswer()
    {
        $plan = $this->fourFifthsExactPlan();
        $files = self::chapterFiles(self::chapterPieces(self::NT));
        $warned = ['status' => 'mismatch', 'items' => self::counts(5, 4, 0, 1, 0)];

        // No text at all: close enough, but nothing covers the mismatch item (Sam, 2026-11-12).
        $this->assertSame($warned, self::bibleAnswer($this->evaluate($plan, [$files], ['exact_threshold' => 80])));
        // Text for MAT 1-4 only: MAT 5:3-10 has no text, so its audio's answer stays.
        $some_text = self::fileset('XN_ET', 'text_plain', self::textPieces(['MAT' => [1, 4]]));
        $this->assertSame($warned, self::bibleAnswer($this->evaluate($plan, [$some_text, $files], ['exact_threshold' => 80])));
        // A plan with no audio at all: nothing to measure, the text answers as without the option.
        $this->assertSame(
            $this->evaluate($plan, [self::text()]),
            $this->evaluate($plan, [self::text()], ['exact_threshold' => 80])
        );
    }

    public function testWithExactThresholdVideoStillLiftsWithAllowVideoOnly()
    {
        // No text; MAT 5:1-16 is mismatch on chapter files and exactly the first clip of MAT 5. Close enough at 80.
        $plan = [$this->item('MAT', 1), $this->item('MAT', 2), $this->item('MAT', 3), $this->item('MAT', 4), $this->item('MAT', 5, 1, 16)];
        $filesets = [self::chapterFiles(self::chapterPieces(self::NT)), self::clips(self::matthewClips())];
        $options = ['include_video' => true, 'exact_threshold' => 80];

        $this->assertSame('mismatch', $this->evaluate($plan, $filesets, $options)['status']);
        $this->assertSame(
            ['status' => 'full', 'items' => self::counts(5, 5, 0, 0, 0)],
            self::bibleAnswer($this->evaluate($plan, $filesets, $options + ['allow_video_only' => true]))
        );
    }

    public function testNoLowerSideSampleDsAudioOnlyBibleStaysMismatchAtEveryThreshold()
    {
        // Revision 5, R25: below the threshold nothing turns exact into mismatch any more. DAONLY on plan 403 (Sample
        // D): its 4 whole chapters stay exact whatever the threshold, 4 of 40.
        $plan = $this->plans()[403];
        foreach ([0, 1, 10, 11, 50, 80, 90, 100] as $threshold) {
            $result = $this->evaluate($plan, self::bibles()['DAONLY'], ['exact_threshold' => $threshold]);
            $this->assertSame(['status' => 'mismatch', 'items' => self::counts(40, 4, 0, 36, 0)], self::bibleAnswer($result), (string) $threshold);
            $this->assertSame(self::counts(40, 4, 0, 36, 0), $result['formats']['audio']['non_drama']['items']);
        }
    }

    public function testSampleGAtNinetyReadsAsWithoutAThresholdForTheBiblesUnderIt()
    {
        // Sample G's notes: at 90, ENGESV (93%) still reads full; NTONLY and DAONLY (87%) read as in Sample A.
        $request = ['plan_ids' => [401], 'bible_ids' => ['ENGESV', 'NTONLY', 'DAONLY']];
        $off = $this->respond($request, $this->plans(), self::bibles());
        $at_90 = $this->respond($request + ['exact_threshold' => 90], $this->plans(), self::bibles());

        $this->assertSame('mismatch', $off['data'][0]['status']);
        $this->assertSame('full', $at_90['data'][0]['status']);
        $this->assertSame($off['data'][1], $at_90['data'][1]);
        $this->assertSame($off['data'][2], $at_90['data'][2]);
        $this->assertSame(['status' => 'partial', 'items' => self::counts(60, 26, 0, 4, 30)], self::bibleAnswer($at_90['data'][1]));
        $this->assertSame(['status' => 'mismatch', 'items' => self::counts(60, 52, 0, 8, 0)], self::bibleAnswer($at_90['data'][2]));
    }

    public function testAllowChapterAudioTurnsEveryMismatchIntoApproximate()
    {
        $files = [self::chapterFiles(self::chapterPieces(self::NT)), self::text()];
        $on = ['allow_chapter_audio' => true];

        $this->assertSame('mismatch', $this->word($this->item('MAT', 5, 3, 10), $files, 'non_drama'));
        $this->assertSame('approximate', $this->word($this->item('MAT', 5, 3, 10), $files, 'non_drama', $on));
        $this->assertSame('exact', $this->word($this->item('MAT', 5), $files, 'non_drama', $on), 'a whole chapter stays exact');

        // Sections: one matching the range stays exact; one running past it, or several lining up with its first and
        // last verse, are approximate, as several sections on a whole chapter are.
        $sections = [self::sections(['LUK' => [[1, 1, 1, 4], [1, 5, 1, 25], [1, 26, 1, 38]]])];
        $this->assertSame('exact', $this->word($this->item('LUK', 1, 5, 25), $sections, 'non_drama', $on));
        $this->assertSame('approximate', $this->word($this->item('LUK', 1, 5, 20), $sections, 'non_drama', $on));
        $this->assertSame('approximate', $this->word($this->item('LUK', 1, 1, 25), $sections, 'non_drama', $on));

        // Partly there: approximate; none of it there: still missing.
        $late = [self::chapterFiles(['MAT' => [[1, 17, 1, null]]]), self::text()];
        $this->assertSame('approximate', $this->word($this->item('MAT', 1, 1, 20), $late, 'non_drama', $on));
        $this->assertSame('missing', $this->word($this->item('MAT', 1, 1, 16), $late, 'non_drama', $on));

        // The item, and its audio as a whole.
        $item = $this->evaluate([$this->item('MAT', 5, 3, 10, 1)], [self::chapterFiles(self::chapterPieces(self::NT))], $on, 1)['item'];
        $this->assertSame('approximate', $item['status']);
        $this->assertSame(['status' => 'approximate', 'non_drama' => 'approximate', 'drama' => 'missing'], $item['formats']['audio']);
    }

    public function testAllowChapterAudioForTheBible()
    {
        $files = self::chapterFiles(self::chapterPieces(self::NT));
        $on = ['allow_chapter_audio' => true];

        // Complete text with chapter-file audio: approximate, not full (audio still outranks text).
        $ranges = [$this->item('MAT', 1, 1, 10), $this->item('MAT', 2, 3, 9), $this->item('MAT', 3)];
        $this->assertSame(
            ['status' => 'approximate', 'items' => self::counts(3, 1, 2, 0, 0)],
            self::bibleAnswer($this->evaluate($ranges, [self::text(), $files], $on))
        );

        // Audio only, with a book missing: partial with the option, where it was incompatible_mismatch.
        $plan = [$this->item('MAT', 1, 1, 10), $this->item('MAT', 2, 3, 9), $this->item('MRK', 1, 1, 5)];
        $this->assertSame('incompatible_mismatch', $this->evaluate($plan, [$files])['status']);
        $this->assertSame(
            ['status' => 'partial', 'items' => self::counts(3, 0, 2, 0, 1)],
            self::bibleAnswer($this->evaluate($plan, [$files], $on))
        );
    }

    public function testWithAllowChapterAudioNoSampleHasAnyMismatch()
    {
        foreach (self::contractSamples() as $name => [$request_json, $response_json]) {
            $request = json_decode($request_json, true);
            $response = json_encode($this->respond($request + ['allow_chapter_audio' => true], $this->plans(), self::bibles()));

            $this->assertDoesNotMatchRegularExpression('/:"(incompatible_)?mismatch"/', $response, $name);
            $this->assertDoesNotMatchRegularExpression('/"mismatch":[1-9]/', $response, $name);

            // Off, sent as false or left out, the answer is the contract's.
            if (!array_key_exists('allow_chapter_audio', $request)) {
                $this->assertSame(
                    json_decode($response_json, true),
                    $this->respond($request + ['allow_chapter_audio' => false], $this->plans(), self::bibles()),
                    $name
                );
            }
        }
    }

    public function testOpusCountsOnlyWithIncludeOpus()
    {
        $opus = self::fileset('XN2DA-opus16', 'audio_drama', self::chapterPieces(self::NT), 'chapter', true);
        $item = $this->item('MAT', 1);

        $this->assertSame('missing', $this->word($item, [$opus], 'drama'));
        $this->assertSame('exact', $this->word($item, [$opus], 'drama', ['include_opus' => true]));
    }

    public function testAnOpusFilesetWithAnMp3TwinChangesNothing()
    {
        $filesets = [
            self::fileset('XN1DA', 'audio', self::chapterPieces(self::NT)),
            self::fileset('XN1DA-opus16', 'audio', self::chapterPieces(self::NT), 'chapter', true),
            self::text(),
        ];
        $plan = $this->quarterExactPlan();

        $this->assertSame(
            $this->evaluate($plan, $filesets),
            $this->evaluate($plan, $filesets, ['include_opus' => true])
        );
    }

    public function testVideoCountsTowardTheBibleOnlyWithAllowVideoOnly()
    {
        // Sam's example: missing in text and audio, exact video.
        $plan = [$this->item('MAT', 1, 1, 17, 7)];
        $clips = self::clips(['MAT' => [[1, 1, 1, 17], [1, 18, 1, 25]]]);

        $without = $this->evaluate($plan, [$clips], ['include_video' => true], 7);
        $with = $this->evaluate($plan, [$clips], ['include_video' => true, 'allow_video_only' => true], 7);

        $this->assertSame('incompatible_missing', $without['status']);
        $this->assertSame('incompatible_missing', $without['item']['status']);
        $this->assertSame('full', $with['status']);
        $this->assertSame(self::counts(1, 1, 0, 0, 0), $with['items']);
        $this->assertSame('full', $with['item']['status']);
        $this->assertSame($without['formats'], $with['formats'], 'video keeps its own answers');
        $this->assertSame($without['item']['formats'], $with['item']['formats']);
        $this->assertSame('exact', $with['item']['formats']['video']);
    }

    public function testAllowVideoOnlyWorksItemByItem()
    {
        // MAT 1 has exact text; MAT 2:1-10 has no text, chapter-file audio (mismatch) and approximate video.
        $plan = [$this->item('MAT', 1, null, null, 1), $this->item('MAT', 2, 1, 10, 2)];
        $filesets = [
            self::fileset('XN_ET', 'text_plain', self::textPieces(['MAT' => [1, 1]])),
            self::chapterFiles(self::chapterPieces(self::NT)),
            self::clips(['MAT' => [[1, 1, 1, 17], [1, 18, 1, 25], [2, 1, 2, 12], [2, 13, 2, 23]]]),
        ];

        $without = $this->evaluate($plan, $filesets, ['include_video' => true], 2);
        $with = $this->evaluate($plan, $filesets, ['include_video' => true, 'allow_video_only' => true], 2);

        $this->assertSame('incompatible_mismatch', $without['item']['status']);
        $this->assertSame('approximate', $with['item']['status']);
        $this->assertSame(self::counts(2, 1, 0, 1, 0), $without['items']);
        $this->assertSame('mismatch', $without['status']);
        $this->assertSame(self::counts(2, 1, 1, 0, 0), $with['items']);
        $this->assertSame('approximate', $with['status']);
        $this->assertSame($without['formats'], $with['formats']);

        // Without video in the answer the option has nothing to add.
        $this->assertSame(
            $this->evaluate($plan, $filesets, [], 2),
            $this->evaluate($plan, $filesets, ['allow_video_only' => true], 2)
        );
    }

    public function testLeavingOutBooksNoPlanTouchesChangesNoAnswer()
    {
        $plans = $this->plans();
        $books = PlanCompatibilityController::planBooks($plans);
        $this->assertSame(['GEN' => true, 'PSA' => true, 'MAT' => true], $books);

        $calculators = [
            new PlanCompatibilityCalculator(true, true, false, 25),
            new PlanCompatibilityCalculator(true, false, true, 0),
            new PlanCompatibilityCalculator(false, false, false, 80, true),
        ];
        foreach ($calculators as $calculator) {
            foreach (self::bibles() as $filesets) {
                $all = $calculator->prepareBible($filesets);
                $some = $calculator->prepareBible($filesets, $books);
                foreach ($plans as $items) {
                    $this->assertSame($calculator->evaluate($items, $all), $calculator->evaluate($items, $some));
                }
            }
        }
        $calculator = new PlanCompatibilityCalculator();
        $this->assertSame([], $calculator->prepareBible([self::text()], ['MRK' => true])['filesets'][0]['chapters']);
    }

    public function testStepOneKeepsOnlyTheCountedKinds()
    {
        $calculator = new PlanCompatibilityCalculator();
        $video = ['set_type_code' => 'video_stream', 'is_opus' => false];

        $this->assertTrue($calculator->keepsFileset(['set_type_code' => 'text_json', 'is_opus' => false]), 'revision 5');
        $this->assertTrue($calculator->keepsFileset(['set_type_code' => 'text_usx', 'is_opus' => false]), 'revision 5');
        $this->assertFalse($calculator->keepsFileset(['set_type_code' => 'audio_hls', 'is_opus' => false]), 'an unknown type');
        $this->assertFalse($calculator->keepsFileset($video));
        $this->assertTrue((new PlanCompatibilityCalculator(true))->keepsFileset($video));
        $this->assertFalse((new PlanCompatibilityCalculator(false, false, true))->keepsFileset($video), 'allow_video_only does not add video');
        $this->assertFalse($calculator->keepsFileset(['set_type_code' => 'audio', 'is_opus' => true]));
        $this->assertTrue((new PlanCompatibilityCalculator(false, true))->keepsFileset(['set_type_code' => 'audio', 'is_opus' => true]));
        $this->assertTrue($calculator->keepsFileset(['set_type_code' => 'audio', 'is_opus' => false]));
        $this->assertTrue($calculator->keepsFileset(['set_type_code' => 'text_format', 'is_opus' => false]));
    }

    public function testDroppedFilesetsDontSetTheLastVerse()
    {
        // Without include_video the clips are absent, so the last verse of LUK 1 comes from the audio alone.
        $sections = self::sections(['LUK' => [[1, 1, 1, 4], [1, 5, 1, 80]]]);
        $clips = self::clips(['LUK' => [[1, 1, 1, 90]]]);

        $this->assertSame('exact', $this->word($this->item('LUK', 1, 5, 80), [$sections, $clips], 'non_drama'));
    }

    public function testIncludeVideoChangesNothingButVideo()
    {
        // No text: the sections end at 80 and a clip at 90. The clip's number sets the last verse for video only.
        $sections = self::sections(['LUK' => [[1, 1, 1, 4], [1, 5, 1, 80]]]);
        $clips = self::clips(['LUK' => [[1, 1, 1, 50], [1, 51, 1, 90]]]);
        $plan = [$this->item('LUK', 1, 5, null, 1), $this->item('LUK', 1, 51, null, 2)];

        $without = $this->evaluate($plan, [$sections, $clips]);
        $with = $this->evaluate($plan, [$sections, $clips], ['include_video' => true]);

        $this->assertSame($without['formats']['audio'], $with['formats']['audio']);
        $this->assertSame($without['status'], $with['status']);
        $this->assertSame($without['items'], $with['items']);
        $this->assertSame(1, $with['formats']['audio']['non_drama']['items']['exact'], 'LUK 1:5 to the end is one section');
        $this->assertSame(1, $with['formats']['video']['items']['exact'], 'LUK 1:51 to the end is one clip');
    }

    public function testAChapterStoredAsSeveralFilesIsOnePieceOnChapterFilesets()
    {
        // MAT 22 stored as two files: the chapter endpoint plays them as one stream of the whole chapter.
        $split = ['MAT' => [[21, 1, 21, null], [22, 1, 22, 22], [22, 23, 22, null]]];
        $chapter = [self::fileset('XN1DA', 'audio', $split), self::text()];

        $this->assertSame('exact', $this->word($this->item('MAT', 22), $chapter, 'non_drama'));
        $this->assertSame('mismatch', $this->word($this->item('MAT', 22, 1, 22), $chapter, 'non_drama'), 'only the whole chapter is served');

        // Duplicate rows of one chapter (old and new file names) are one piece too.
        $duplicates = [self::fileset('XN1DA', 'audio', ['MAT' => [[5, 1, 5, null], [5, 1, 5, null]]]), self::text()];
        $this->assertSame('exact', $this->word($this->item('MAT', 5), $duplicates, 'non_drama'));
    }

    public function testSectionsAndOlderFilesetsWithDistinctStartsAreNotJoined()
    {
        $split = ['MAT' => [[22, 1, 22, 22], [22, 23, 22, null]]];

        $section = [self::sections($split), self::text()];
        $this->assertSame('approximate', $this->word($this->item('MAT', 22), $section, 'non_drama'));
        $this->assertSame('exact', $this->word($this->item('MAT', 22, 1, 22), $section, 'non_drama'));

        // No segmentation recorded and files starting on different verses: the endpoint treats them as sections, so a
        // whole chapter is approximate, as on a section fileset.
        $older = [self::fileset('XN1DA', 'audio', $split, null), self::text()];
        $this->assertSame('exact', $this->word($this->item('MAT', 22, 1, 22), $older, 'non_drama'));
        $this->assertSame('approximate', $this->word($this->item('MAT', 22), $older, 'non_drama'));
        $this->assertSame('mismatch', $this->word($this->item('MAT', 22, 1, 10), $older, 'non_drama'));

        // Streams are never joined: they are cut anywhere anyway.
        $stream = [self::fileset('XN1SA', 'audio_stream', $split), self::text()];
        $this->assertSame('exact', $this->word($this->item('MAT', 22, 1, 22), $stream, 'non_drama'));
    }

    public function testMismatchMeansTheItemStartsOrEndsInsideAChapter()
    {
        // A chapter file that runs on into the next chapter (2 such files on dev): the whole chapter is covered, not
        // exactly, and starts and ends on the chapter's edges, so it is approximate, not mismatch.
        $runs_on = [self::fileset('XN1DA', 'audio', ['MAT' => [[21, 1, 22, null], [23, 1, 23, null]]]), self::text()];
        $this->assertSame('approximate', $this->word($this->item('MAT', 21), $runs_on, 'non_drama'));
        $this->assertSame('exact', $this->word($this->item('MAT', 21, null, null, null, 22), $runs_on, 'non_drama'), 'MAT 21-22, the file exactly');
        $this->assertSame('exact', $this->word($this->item('MAT', 23), $runs_on, 'non_drama'));
        $this->assertSame('mismatch', $this->word($this->item('MAT', 21, 1, 10), $runs_on, 'non_drama'));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Roll-ups

    public function testTheSixStatuses()
    {
        $status = function (int $exact, int $approximate, int $mismatch, int $missing) {
            return PlanCompatibilityCalculator::status(self::counts($exact + $approximate + $mismatch + $missing, $exact, $approximate, $mismatch, $missing));
        };

        $this->assertSame('full', $status(3, 0, 0, 0));
        $this->assertSame('full', $status(0, 0, 0, 0), 'an empty plan');
        $this->assertSame('approximate', $status(1, 2, 0, 0));
        $this->assertSame('approximate', $status(0, 3, 0, 0));
        $this->assertSame('mismatch', $status(1, 0, 2, 0));
        $this->assertSame('mismatch', $status(0, 1, 2, 0));
        $this->assertSame('partial', $status(1, 0, 0, 2));
        $this->assertSame('partial', $status(0, 1, 1, 1), 'something missing and something playable');
        $this->assertSame('incompatible_mismatch', $status(0, 0, 3, 0), 'only mismatch items');
        $this->assertSame('incompatible_mismatch', $status(0, 0, 1, 2), 'mismatch and missing, nothing playable');
        $this->assertSame('incompatible_missing', $status(0, 0, 0, 3));
    }

    public function testAnEmptyPlanIsFull()
    {
        $result = $this->evaluate([], [self::text()], ['include_video' => true]);
        $zero = self::counts(0, 0, 0, 0, 0);

        $this->assertSame('full', $result['status']);
        $this->assertSame($zero, $result['items']);
        foreach (['text', 'video'] as $format) {
            $this->assertSame(['status' => 'full', 'items' => $zero], $result['formats'][$format]);
        }
        $this->assertSame('full', $result['formats']['audio']['status']);
        $this->assertSame('full', $result['formats']['audio']['drama']['status']);
    }

    public function testSamsExampleExactTextNoLongerHidesMismatchedAudio()
    {
        // 10 verse ranges, all text exact. The audio is an NT stream (4 items) and OT chapter files (6 items).
        $plan = [];
        foreach ([1, 2, 3, 4] as $chapter) {
            $plan[] = $this->item('MAT', $chapter, 2, 8);
        }
        foreach ([1, 2, 3, 4, 5, 6] as $chapter) {
            $plan[] = $this->item('GEN', $chapter, 2, 8);
        }
        $filesets = [
            self::text(self::OT + self::NT),
            self::fileset('XN1SA', 'audio_stream', self::chapterPieces(self::NT)),
            self::chapterFiles(self::chapterPieces(self::OT)),
        ];

        $result = $this->evaluate($plan, $filesets);

        $this->assertSame(['status' => 'full', 'items' => self::counts(10, 10, 0, 0, 0)], $result['formats']['text']);
        $this->assertSame('mismatch', $result['formats']['audio']['status']);
        $this->assertSame(self::counts(10, 4, 0, 6, 0), $result['formats']['audio']['items']);
        $this->assertSame('mismatch', $result['status'], 'not full: the audio decides where there is audio');
        $this->assertSame(self::counts(10, 4, 0, 6, 0), $result['items']);
    }

    public function testCompleteTextWithOnlyMismatchedAudioIsMismatchNeverIncompatible()
    {
        $plan = [$this->item('MAT', 1, 1, 10), $this->item('MAT', 2, 3, 9), $this->item('MAT', 3, 4, 8)];
        $files = self::chapterFiles(self::chapterPieces(self::NT));

        $with_text = $this->evaluate($plan, [self::text(), $files]);
        $this->assertSame(['status' => 'incompatible_mismatch', 'items' => self::counts(3, 0, 0, 3, 0)], array_intersect_key($with_text['formats']['audio'], ['status' => 1, 'items' => 1]));
        $this->assertSame('mismatch', $with_text['status'], 'the text is playable');
        $this->assertSame(self::counts(3, 0, 0, 3, 0), $with_text['items']);
        $this->assertSame($with_text['formats']['audio']['items']['mismatch'], $with_text['items']['mismatch']);

        // With an item nothing has, it is partial.
        $with_missing = $this->evaluate(array_merge($plan, [$this->item('MRK', 1)]), [self::text(), $files]);
        $this->assertSame('partial', $with_missing['status']);
        $this->assertSame(self::counts(4, 0, 0, 3, 1), $with_missing['items']);

        // Without the text, nothing is playable.
        $without_text = $this->evaluate($plan, [$files]);
        $this->assertSame('incompatible_mismatch', $without_text['status']);

        // The single item: mismatch while its text is exact; its audio, incompatible_mismatch.
        $item = $this->evaluate([$plan[0]], [self::text(), $files], [], $plan[0]['id'])['item'];
        $this->assertSame('mismatch', $item['status']);
        $this->assertSame(['status' => 'incompatible_mismatch', 'non_drama' => 'mismatch', 'drama' => 'missing'], $item['formats']['audio']);
        $this->assertSame('exact', $item['formats']['text']);
        $this->assertSame('incompatible_mismatch', $this->evaluate([$plan[0]], [$files], [], $plan[0]['id'])['item']['status']);
    }

    public function testApproximateAudioOutranksExactTextToo()
    {
        $plan = [$this->item('LUK', 1)];
        $filesets = [self::text(['LUK' => [1, 24]]), self::sections(['LUK' => [[1, 1, 1, 4], [1, 5, 1, 31]]])];

        $result = $this->evaluate($plan, $filesets, [], $plan[0]['id']);

        $this->assertSame('full', $result['formats']['text']['status']);
        $this->assertSame('approximate', $result['formats']['audio']['status']);
        $this->assertSame(['status' => 'approximate', 'items' => self::counts(1, 0, 1, 0, 0)], array_intersect_key($result, ['status' => 1, 'items' => 1]));
        $this->assertSame('approximate', $result['item']['status']);
    }

    public function testWithAllowVideoOnlyExactVideoLiftsAnItemPastMismatchedAudio()
    {
        // MAT 5:1-16 is exactly the first clip of MAT 5; on chapter files it is mismatch; the text is exact.
        $plan = [$this->item('MAT', 5, 1, 16)];
        $filesets = [self::text(), self::chapterFiles(self::chapterPieces(self::NT)), self::clips(self::matthewClips())];

        $without = $this->evaluate($plan, $filesets, ['include_video' => true], $plan[0]['id']);
        $this->assertSame('mismatch', $without['status']);
        $this->assertSame('mismatch', $without['item']['status']);

        $with = $this->evaluate($plan, $filesets, ['include_video' => true, 'allow_video_only' => true], $plan[0]['id']);
        $this->assertSame('full', $with['status'], 'video is not outranked by audio');
        $this->assertSame(self::counts(1, 1, 0, 0, 0), $with['items']);
        $this->assertSame('full', $with['item']['status']);
        $this->assertSame($without['formats'], $with['formats']);
        $this->assertSame($without['item']['formats'], $with['item']['formats']);
    }

    public function testTheBibleIsTheUnionOfTextAndAudio()
    {
        $plan = [$this->item('GEN', 1), $this->item('GEN', 2), $this->item('MAT', 1), $this->item('MAT', 2)];
        $filesets = [
            self::fileset('XO_ET', 'text_plain', self::textPieces(['GEN' => [1, 50]])),
            self::fileset('XN1DA', 'audio', self::chapterPieces(self::NT)),
        ];

        $result = $this->evaluate($plan, $filesets);

        $this->assertSame('partial', $result['formats']['text']['status']);
        $this->assertSame('partial', $result['formats']['audio']['status']);
        $this->assertSame('full', $result['status']);
        $this->assertSame(self::counts(4, 4, 0, 0, 0), $result['items']);
    }

    public function testTheItemsStatusWords()
    {
        $filesets = [
            self::chapterFiles(self::chapterPieces(self::NT)),
            self::sections(['LUK' => [[1, 1, 1, 4], [1, 5, 1, 80]]], 'audio_drama'),
        ];
        $item = function (array $item) use ($filesets) {
            $item['id'] = 1;
            return $this->evaluate([$item], $filesets, [], 1)['item'];
        };

        $exact = $item($this->item('MAT', 5));
        $this->assertSame(['full', 'full'], [$exact['status'], $exact['formats']['audio']['status']]);

        $approximate = $item($this->item('LUK', 1));
        $this->assertSame(['approximate', 'approximate'], [$approximate['status'], $approximate['formats']['audio']['status']]);
        $this->assertSame(['status' => 'approximate', 'non_drama' => 'missing', 'drama' => 'approximate'], $approximate['formats']['audio']);

        $mismatch = $item($this->item('MAT', 5, 3, 10));
        $this->assertSame(['incompatible_mismatch', 'incompatible_mismatch'], [$mismatch['status'], $mismatch['formats']['audio']['status']]);
        // With exact text, the item is playable through it: mismatch, never partial.
        $with_text = $this->evaluate([$this->item('MAT', 5, 3, 10, 1)], array_merge($filesets, [self::text()]), [], 1)['item'];
        $this->assertSame(['mismatch', 'incompatible_mismatch'], [$with_text['status'], $with_text['formats']['audio']['status']]);

        $missing = $item($this->item('MRK', 1));
        $this->assertSame('incompatible_missing', $missing['status']);
        $this->assertSame(
            ['text' => 'missing', 'audio' => ['status' => 'incompatible_missing', 'non_drama' => 'missing', 'drama' => 'missing']],
            $missing['formats']
        );
    }

    public function testKeysAndOrder()
    {
        $plan = [$this->item('MAT', 1, null, null, 7)];

        $plain = $this->evaluate($plan, [self::text()]);
        $this->assertSame(['status', 'items', 'formats'], array_keys($plain));
        $this->assertSame(['text', 'audio'], array_keys($plain['formats']));
        $this->assertSame(['status', 'items', 'non_drama', 'drama'], array_keys($plain['formats']['audio']));
        $this->assertSame(['total', 'exact', 'approximate', 'mismatch', 'missing'], array_keys($plain['items']));

        $calculator = new PlanCompatibilityCalculator(true);
        $full = $calculator->evaluate($plan, $calculator->prepareBible([self::text()]), [7]);
        $this->assertSame(['status', 'items', 'formats', 'playlist_items'], array_keys($full));
        $this->assertSame(['text', 'audio', 'video'], array_keys($full['formats']));
        $this->assertSame(['playlist_item_id', 'status', 'formats'], array_keys($full['playlist_items'][0]));
        $this->assertSame(['text', 'audio', 'video'], array_keys($full['playlist_items'][0]['formats']));
        $this->assertSame(['status', 'non_drama', 'drama'], array_keys($full['playlist_items'][0]['formats']['audio']));

        $none = $calculator->evaluate($plan, $calculator->prepareBible([self::text()]), [8]);
        $this->assertSame([], $none['playlist_items'], 'an item not in this plan');
        $this->assertArrayNotHasKey('playlist_items', $calculator->evaluate($plan, $calculator->prepareBible([self::text()])));
    }

    public function testABibleWithNoneOfTheKindsAskedForIsMissingEverywhere()
    {
        $clips = self::clips(['MAT' => [[1, 1, 1, 25]]]);

        $result = $this->evaluate([$this->item('MAT', 1)], [$clips]);

        $this->assertSame('incompatible_missing', $result['status']);
        $this->assertSame('incompatible_missing', $result['formats']['text']['status']);
        $this->assertSame('incompatible_missing', $result['formats']['audio']['status']);
        $this->assertArrayNotHasKey('video', $result['formats']);
    }

    public function testPlaylistItemsLandOnTheirOwnPlansEntriesInTheOrderAsked()
    {
        $plan_1 = $this->quarterExactPlan();
        $plan_2 = [$this->item('MAT', 1, null, null, 21), $this->item('MAT', 2, null, null, 22)];
        $plan_3 = [$this->item('MAT', 3, null, null, 31)];
        $plans = [2 => $plan_2, 1 => $plan_1, 3 => $plan_3];
        $calculator = new PlanCompatibilityCalculator();
        $bible = $calculator->prepareBible([self::chapterFiles(self::chapterPieces(self::NT))]);

        // Asked: plan 2's second item, plan 1's first (a whole chapter) and second, then plan 2's first.
        $normalized = PlanCompatibilityController::normalizeRequest([
            'plan_ids'          => [2, 1, 3],
            'bible_ids'         => ['1234'],
            'playlist_item_ids' => [22, $plan_1[0]['id'], $plan_1[1]['id'], 22, 21],
        ]);
        $this->assertSame([], $normalized['errors']);
        $asked = $normalized['values']['playlist_item_ids'];
        $this->assertSame([22, $plan_1[0]['id'], $plan_1[1]['id'], 21], $asked, 'repeats count once');
        $this->assertSame([], PlanCompatibilityController::unknownPlaylistItemIds($plans, $asked));
        $this->assertSame([99], PlanCompatibilityController::unknownPlaylistItemIds($plans, [21, 99]));

        $results = PlanCompatibilityController::bibleResults($calculator, $plans, $bible, $asked);
        $entries = PlanCompatibilityController::entries($plans, [['1234', $results]]);

        $this->assertSame([2, 1, 3], array_column($entries, 'plan_id'), 'plans in request order');
        $this->assertSame(['1234', '1234', '1234'], array_column($entries, 'bible_id'), 'an all-digit Bible id stays a string');
        $this->assertSame([22, 21], array_column($entries[0]['playlist_items'], 'playlist_item_id'), 'the order asked');
        $this->assertSame([$plan_1[0]['id'], $plan_1[1]['id']], array_column($entries[1]['playlist_items'], 'playlist_item_id'));
        $this->assertSame([], $entries[2]['playlist_items'], 'none asked in plan 3');
        $this->assertSame('exact', $entries[1]['playlist_items'][0]['formats']['audio']['non_drama'], 'MAT 1');
        $this->assertSame('mismatch', $entries[1]['playlist_items'][1]['formats']['audio']['non_drama'], 'MAT 2:1-10');
        $this->assertSame('exact', $entries[0]['playlist_items'][0]['formats']['audio']['non_drama']);

        // No ids, or an empty list: no playlist_items anywhere.
        foreach ([null, []] as $none) {
            $values = PlanCompatibilityController::normalizeRequest([
                'plan_ids' => [2], 'bible_ids' => ['X'], 'playlist_item_ids' => $none,
            ])['values'];
            $this->assertNull($values['playlist_item_ids']);
            $results = PlanCompatibilityController::bibleResults($calculator, [2 => $plan_2], $bible, $values['playlist_item_ids']);
            $this->assertArrayNotHasKey('playlist_items', $results[2]);
        }
    }
}
