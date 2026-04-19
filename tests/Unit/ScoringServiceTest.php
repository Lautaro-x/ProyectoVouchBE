<?php

namespace Tests\Unit;

use App\Services\ScoringService;
use PHPUnit\Framework\TestCase;

class ScoringServiceTest extends TestCase
{
    private ScoringService $scoring;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoring = new ScoringService();
    }

    public function test_letter_grade_boundaries(): void
    {
        $cases = [
            [10.0, 'S'],
            [9.9,  'A+'],
            [9.1,  'A+'],
            [9.0,  'A'],
            [8.9,  'B+'],
            [8.1,  'B+'],
            [8.0,  'B'],
            [7.9,  'C+'],
            [7.1,  'C+'],
            [7.0,  'C'],
            [6.9,  'D+'],
            [6.1,  'D+'],
            [6.0,  'D'],
            [5.9,  'E+'],
            [5.1,  'E+'],
            [5.0,  'E'],
            [4.9,  'F'],
            [1.0,  'F'],
            [0.0,  'F'],
        ];

        foreach ($cases as [$score, $expected]) {
            $this->assertSame(
                $expected,
                $this->scoring->calculateLetterGrade($score),
                "Score {$score} should be {$expected}"
            );
        }
    }

    public function test_99_is_aplus_not_s(): void
    {
        $this->assertSame('A+', $this->scoring->calculateLetterGrade(9.9));
    }

    public function test_89_is_bplus_not_a(): void
    {
        $this->assertSame('B+', $this->scoring->calculateLetterGrade(8.9));
    }

    public function test_perfect_score_is_s(): void
    {
        $this->assertSame('S', $this->scoring->calculateLetterGrade(10.0));
    }
}
