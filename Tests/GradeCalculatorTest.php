<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\tests\GradeCalculatorTest.php

use PHPUnit\Framework\TestCase;
use App\GradeCalculator; // Import the service class you're testing

class GradeCalculatorTest extends TestCase
{
    private $calculator;

    protected function setUp(): void
    {
        $this->calculator = new GradeCalculator();
    }

    // --- Test Cases for calculateSimpleAverage ---

    public function testCalculateSimpleAverageOfPositiveGrades()
    {
        $grades = [80, 90, 70, 100];
        $this->assertEquals(85.0, $this->calculator->calculateSimpleAverage($grades));
    }

    public function testCalculateSimpleAverageWithEmptyGradesReturnsZero()
    {
        $grades = [];
        $this->assertEquals(0.0, $this->calculator->calculateSimpleAverage($grades));
    }

    public function testCalculateSimpleAverageWithSingleGrade()
    {
        $grades = [95];
        $this->assertEquals(95.0, $this->calculator->calculateSimpleAverage($grades));
    }

    public function testCalculateSimpleAverageWithGradesIncludingZero()
    {
        $grades = [0, 100, 50];
        $this->assertEquals(50.0, $this->calculator->calculateSimpleAverage($grades));
    }

    // --- Test Cases for calculateWeightedAverage ---

    public function testCalculateWeightedAverageCorrectly()
    {
        $weightedGrades = [
            ['score' => 80, 'weight' => 0.4], // 40% of grade
            ['score' => 90, 'weight' => 0.6]  // 60% of grade
        ];
        // (80 * 0.4) + (90 * 0.6) = 32 + 54 = 86
        $this->assertEquals(86.0, $this->calculator->calculateWeightedAverage($weightedGrades));
    }

    public function testCalculateWeightedAverageWithDifferentWeights()
    {
        $weightedGrades = [
            ['score' => 70, 'weight' => 2],
            ['score' => 90, 'weight' => 1]
        ];
        // (70*2) + (90*1) = 140 + 90 = 230
        // Total weight = 2 + 1 = 3
        // 230 / 3 = 76.666...
        // Round the actual result to 3 decimal places before comparing
        $actualResult = $this->calculator->calculateWeightedAverage($weightedGrades);
        $this->assertEquals(76.667, round($actualResult, 3)); // Expected should be 76.667 when rounded
    }

    public function testCalculateWeightedAverageWithEmptyGradesReturnsZero()
    {
        $weightedGrades = [];
        $this->assertEquals(0.0, $this->calculator->calculateWeightedAverage($weightedGrades));
    }

    public function testCalculateWeightedAverageWithZeroTotalWeightReturnsZero()
    {
        $weightedGrades = [
            ['score' => 80, 'weight' => 0],
            ['score' => 90, 'weight' => 0]
        ];
        $this->assertEquals(0.0, $this->calculator->calculateWeightedAverage($weightedGrades));
    }

    public function testCalculateWeightedAverageHandlesInvalidScoresOrWeights()
    {
        $weightedGrades = [
            ['score' => 80, 'weight' => 0.5],
            ['score' => -10, 'weight' => 0.5], // Invalid score
            ['score' => 90, 'weight' => -0.2] // Invalid weight
        ];
        // Only 80 * 0.5 = 40.0 should be calculated from valid items.
        // Total valid weight = 0.5
        $this->assertEquals(80.0, $this->calculator->calculateWeightedAverage($weightedGrades));
    }

    // --- Test Cases for isValidGrade ---

    public function testIsValidGradeReturnsTrueForValidRange()
    {
        $this->assertTrue($this->calculator->isValidGrade(0));
        $this->assertTrue($this->calculator->isValidGrade(50));
        $this->assertTrue($this->calculator->isValidGrade(100));
    }

    public function testIsValidGradeReturnsFalseForInvalidRange()
    {
        $this->assertFalse($this->calculator->isValidGrade(-1));
        $this->assertFalse($this->calculator->isValidGrade(101));
    }
}
