<?php
namespace Tests\Service;

use DateTime;
use DateTimeZone;

use App\Model\Entity\Event\Event;

class EventRepositoryTest extends EventDbTestCase
{

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testCreateAndGetAll()
    {
        // 1. Create an Event
        $appointmentDate = new DateTime('2025-12-31 10:00:00', new DateTimeZone('Europe/Bratislava'));

        $event = new Event(
            patientName: 'John Doe',
            phoneNumber: '+1234567890',
            doctorName: 'Dr. House',
            doctorAddress: 'Princeton-Plainsboro',
            appointmentDate: $appointmentDate,
        );

        $this->eventRepository->insert($event);

        $this->assertNotNull($event->id);

        // 2. Fetch and verify the event
        $fetchedEvent = $this->fetchSingleEvent();

        $this->assertEquals('John Doe', $fetchedEvent->patientName);
        $this->assertEquals('+1234567890', $fetchedEvent->phoneNumber);
        $this->assertEquals('Dr. House', $fetchedEvent->doctorName);
        $this->assertEquals('Princeton-Plainsboro', $fetchedEvent->doctorAddress);
        
        // Check Date Equality (comparing timestamps or formatted strings to avoid object identity issues)
        $this->assertEquals(
            $appointmentDate->format('Y-m-d H:i:s'), 
            $fetchedEvent->appointmentDate->format('Y-m-d H:i:s')
        );

        // Check Created At is set and is recent
        $this->assertNotNull($fetchedEvent->createdAt);
        $this->assertEquals('Europe/Bratislava', $fetchedEvent->createdAt->getTimezone()->getName());
        $this->assertNotNull($fetchedEvent->updatedAt);
        $this->assertEquals('Europe/Bratislava', $fetchedEvent->updatedAt->getTimezone()->getName());
    }

}
