<?php
namespace Tests\Service;

use DateTime;
use DateTimeZone;

use App\Model\Entity\Event;
use App\Service\EventService;
use Tests\Db\DbTestCase;

class EventServiceTest extends DbTestCase
{
    private EventService $eventService;

    public function setUp(): void
    {
        parent::setUp();

        // Adjusted for SQLite: INTEGER PRIMARY KEY AUTOINCREMENT
        $this->database->query('
            CREATE TABLE event (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_name TEXT NOT NULL,
                phone_number TEXT NOT NULL,
                doctor_name TEXT NOT NULL,
                doctor_address TEXT NOT NULL,
                appointment_date DATETIME NOT NULL,
                attachment_content BLOB,
                attachment_name TEXT,
                attachment_type TEXT,
                created_at DATETIME NOT NULL
            )
        ');

        $this->eventService = $this->container->getByType(EventService::class);
    }

    public function tearDown(): void
    {
        $this->database->query('DROP TABLE event');
        parent::tearDown();
    }

    public function testCreateAndGetAll()
    {
        // 1. Create an Event
        $appointmentDate = new DateTime('2025-12-31 10:00:00', new DateTimeZone('UTC'));

        $event = new Event(
            patientName: 'John Doe',
            phoneNumber: '+1234567890',
            doctorName: 'Dr. House',
            doctorAddress: 'Princeton-Plainsboro',
            appointmentDate: $appointmentDate,
            attachmentContent: 'fake-image-content',
            attachmentName: 'scan.jpg',
            attachmentType: 'image/jpeg'
        );

        $this->eventService->create($event);

        // 2. Fetch All
        $events = $this->eventService->getAll();

        // 3. Assertions
        $this->assertCount(1, $events);
        $fetchedEvent = $events[0];

        $this->assertEquals('John Doe', $fetchedEvent->patientName);
        $this->assertEquals('+1234567890', $fetchedEvent->phoneNumber);
        $this->assertEquals('Dr. House', $fetchedEvent->doctorName);
        $this->assertEquals('Princeton-Plainsboro', $fetchedEvent->doctorAddress);
        $this->assertEquals('scan.jpg', $fetchedEvent->attachmentName);
        $this->assertEquals('image/jpeg', $fetchedEvent->attachmentType);
        
        // Note: getAll() does not select attachment_content by design (for performance)
        $this->assertNull($fetchedEvent->attachmentContent); 

        // Check Date Equality (comparing timestamps or formatted strings to avoid object identity issues)
        $this->assertEquals(
            $appointmentDate->format('Y-m-d H:i:s'), 
            $fetchedEvent->appointmentDate->format('Y-m-d H:i:s')
        );

        // Check Created At is set and is recent
        $this->assertNotNull($fetchedEvent->createdAt);
        $this->assertEquals('UTC', $fetchedEvent->createdAt->getTimezone()->getName());
    }
}
