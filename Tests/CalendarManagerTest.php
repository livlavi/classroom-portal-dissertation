<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\tests\CalendarManagerTest.php

use PHPUnit\Framework\TestCase;
use App\CalendarManager;

class CalendarManagerTest extends TestCase
{
    private $manager;

    protected function setUp(): void
    {
        $this->manager = new CalendarManager();
    }

    // --- Test Cases for addEvent ---

    public function testCanAddEventWithValidData()
    {
        $eventId = $this->manager->addEvent(1, 'New Event', 'Event description', '2025-07-01 09:00:00', '2025-07-01 10:00:00', 'appointment');
        $this->assertIsInt($eventId);
        $this->assertGreaterThan(0, $eventId);
    }

    public function testCannotAddEventWithInvalidUserId()
    {
        $this->assertFalse($this->manager->addEvent(0, 'Event', 'Desc', '2025-07-01 09:00:00', '2025-07-01 10:00:00', 'appointment'));
    }

    public function testCannotAddEventWithEmptyTitle()
    {
        $this->assertFalse($this->manager->addEvent(1, '', 'Desc', '2025-07-01 09:00:00', '2025-07-01 10:00:00', 'appointment'));
    }

    public function testCannotAddEventWithInvalidStartOrEndTimeFormat()
    {
        $this->assertFalse($this->manager->addEvent(1, 'Event', 'Desc', 'invalid-time', '2025-07-01 10:00:00', 'appointment'));
        $this->assertFalse($this->manager->addEvent(1, 'Event', 'Desc', '2025-07-01 09:00:00', 'invalid-time', 'appointment'));
    }

    public function testCannotAddEventWhereStartTimeIsAfterEndTime()
    {
        $this->assertFalse($this->manager->addEvent(1, 'Event', 'Desc', '2025-07-01 10:00:00', '2025-07-01 09:00:00', 'appointment'));
    }

    public function testCannotAddEventWhereStartTimeIsSameAsEndTime()
    {
        $this->assertFalse($this->manager->addEvent(1, 'Event', 'Desc', '2025-07-01 10:00:00', '2025-07-01 10:00:00', 'appointment'));
    }

    // --- Test Cases for getEventsForUser ---

    public function testGetEventsForUserReturnsCorrectEventsInDateRange()
    {
        $events = $this->manager->getEventsForUser(1, '2025-06-05', '2025-06-06');
        $this->assertCount(2, $events);
        $this->assertEquals('Math Class', $events[0]['title']);
        $this->assertEquals('Study Session', $events[1]['title']);
    }

    public function testGetEventsForUserReturnsEmptyArrayIfNoEvents()
    {
        $events = $this->manager->getEventsForUser(999, '2025-06-01', '2025-06-30'); // Non-existent user
        $this->assertEmpty($events);
    }

    public function testGetEventsForUserReturnsEmptyArrayIfNoEventsInDateRange()
    {
        $events = $this->manager->getEventsForUser(1, '2026-01-01', '2026-01-31'); // Date range outside dummy data
        $this->assertEmpty($events);
    }

    public function testGetEventsForUserHandlesSingleDayRange()
    {
        $events = $this->manager->getEventsForUser(1, '2025-06-10', '2025-06-10');
        $this->assertCount(1, $events);
        $this->assertEquals('Project Due', $events[0]['title']);
    }

    public function testGetEventsForUserReturnsEventsOnlyForSpecifiedUser()
    {
        $events = $this->manager->getEventsForUser(2, '2025-06-01', '2025-06-30');
        $this->assertCount(1, $events);
        $this->assertEquals('Meeting', $events[0]['title']);
        $this->assertEquals(2004, $events[0]['id']);
    }
}
