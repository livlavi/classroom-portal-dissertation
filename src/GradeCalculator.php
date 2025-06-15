<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\src\GradeCalculator.php

namespace App;

class GradeCalculator
{
    /**
     * Calculates the simple average of an array of grades.
     * @param array $grades An array of numeric grades.
     * @return float The average grade, or 0.0 if no grades.
     */
    public function calculateSimpleAverage(array $grades): float
    {
        if (empty($grades)) {
            return 0.0;
        }

        $sum = array_sum($grades);
        return (float) ($sum / count($grades));
    }

    /**
     * Calculates the weighted average of grades.
     * Each grade should be an associative array with 'score' and 'weight'.
     * Example: [['score' => 80, 'weight' => 0.4], ['score' => 90, 'weight' => 0.6]]
     * @param array $weightedGrades An array of ['score' => int, 'weight' => float].
     * @return float The weighted average grade, or 0.0 if no grades or total weight is 0.
     */
    public function calculateWeightedAverage(array $weightedGrades): float
    {
        if (empty($weightedGrades)) {
            return 0.0;
        }

        $totalScore = 0.0;
        $totalWeight = 0.0;

        foreach ($weightedGrades as $gradeItem) {
            $score = $gradeItem['score'] ?? 0;
            $weight = $gradeItem['weight'] ?? 0;

            // Basic validation for score and weight
            if (
                !is_numeric($score) || $score < 0 || $score > 100 ||
                !is_numeric($weight) || $weight < 0
            ) {
                // In a real app, you might throw an exception or handle error.
                // For this example, we'll skip invalid entries.
                continue;
            }

            $totalScore += ($score * $weight);
            $totalWeight += $weight;
        }

        if ($totalWeight === 0.0) {
            return 0.0; // Avoid division by zero
        }

        return (float) ($totalScore / $totalWeight);
    }

    /**
     * Validates if a single grade is within the valid range (0-100).
     * @param int $grade The grade to validate.
     * @return bool True if valid, false otherwise.
     */
    public function isValidGrade(int $grade): bool
    {
        return $grade >= 0 && $grade <= 100;
    }

    // You might add methods here for:
    // - Rounding grades to nearest integer/decimal
    // - Calculating letter grades
    // - Handling bonus points or deductions
    // - etc.
}
