<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\tests\ExampleTest.php

use PHPUnit\Framework\TestCase; // 1. Import the TestCase class

class ExampleTest extends TestCase // 2. Your test class extends TestCase
{
    // 3. Each method starting with 'test' is a separate test case
    public function testThatTrueIsTrue(): void
    {
        // 4. This is an "assertion" - it checks if a condition is true
        $this->assertTrue(true);
    }

    public function testThatStringsAreEqual(): void
    {
        $expected = "Hello";
        $actual = "Hello";
        $this->assertEquals($expected, $actual, "The strings should be equal");
    }
}
