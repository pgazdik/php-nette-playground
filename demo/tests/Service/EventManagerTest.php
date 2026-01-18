<?php
namespace Tests\Service;

use App\Model\Entity\Event\Event;
use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Model\Entity\Event\NotificationType;
use App\Service\EventManager;
use App\Service\NotificationMsgRepository;

use DateTime;
use DateTimeZone;

class EventManagerTest extends EventDbTestCase
{
    private EventManager $eventManager;

    public function setUp(): void
    {
        parent::setUp();
        $this->eventManager = $this->container->getByType(EventManager::class);
    }

    public function testCreateEventCreatesNotifications()
    {
        // 1. Prepare Event Data
        // Appointment is 10 days in the future, so notification should be 7 days before (3 days from now)
        $appointmentDate = new DateTime('+10 days', new DateTimeZone('Europe/Bratislava'));

        $event = new Event(
            patientName: 'Integration Tester',
            phoneNumber: '+421900000000',
            doctorName: 'Dr. Integration',
            doctorAddress: 'Test Lab 1',
            appointmentDate: $appointmentDate,
            attachmentContent: 'fake-image-data',
            attachmentName: 'test.jpg',
            attachmentType: 'image/jpeg'
        );

        // 2. Call the Manager
        $this->eventManager->createEvent($event);

        // 3. Verify Event in DB
        $event = $this->fetchSingleEvent();

        $this->assertEquals('Integration Tester', $event->patientName);

        // 4. Verify Notifications in DB
        // We expect 2 notifications: 1 Main/Text and 1 Main/Image (since we provided attachment)
        $notifications = $this->database->table('notification_msg')
            ->where('event_id', $event->id)
            ->order('id ASC')
            ->fetchAll();

        $this->assertCount(2, $notifications, 'Should create 2 notifications');

        // Verify Text Notification
        $textMsg = $notifications[1];
        $this->assertEquals(1, $textMsg->msg_index);
        $this->assertEquals(NotificationType::Main->value, $textMsg->notification_type);
        $this->assertEquals(MediaType::Text->value, $textMsg->media_type);
        $this->assertStringContainsString('Integration Tester', $textMsg->text);

        // Verify Image Notification
        $imageMsg = $notifications[2];
        $this->assertEquals(2, $imageMsg->msg_index);
        $this->assertEquals(NotificationType::Main->value, $imageMsg->notification_type);
        $this->assertEquals(MediaType::Image->value, $imageMsg->media_type);
        $this->assertEquals('', $imageMsg->text);

        // Verify Scheduling Logic
        // Expected scheduledAt is 7 days before appointment
        $expectedscheduledAtBa = (clone $appointmentDate)->modify('-7 days');

        $dbscheduledAt = new DateTime($textMsg->scheduled_at);

        // Allow 1 minute variance
        $diff = abs($dbscheduledAt->getTimestamp() - $expectedscheduledAtBa->getTimestamp());
        $this->assertLessThan(60, $diff, 'Send time should be approx 7 days before appointment');
    }

}
