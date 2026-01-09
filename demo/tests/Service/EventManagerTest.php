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
    private NotificationMsgRepository $otificationMsgRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->dropAllTables();

        $this->createEventTable();
        $this->createNotificationTable();
        $this->createNotificationAttemptTable();

        $this->eventManager = $this->container->getByType(EventManager::class);
        $this->otificationMsgRepository = $this->container->getByType(NotificationMsgRepository::class);
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
        // Expected sendAt is 7 days before appointment
        $expectedSendAtBa = (clone $appointmentDate)->modify('-7 days');

        $dbSendAt = new DateTime($textMsg->send_at);

        // Allow 1 minute variance
        $diff = abs($dbSendAt->getTimestamp() - $expectedSendAtBa->getTimestamp());
        $this->assertLessThan(60, $diff, 'Send time should be approx 7 days before appointment');
    }

    public function test_ApproveNotification()
    {
        // 1. Create Event 1 with 2 notifications
        $event1 = $this->createTestEvent('Event 1', new DateTime('+1 day'));
        $eventId1 = $this->eventRepository->create($event1);

        $msg1_1 = $this->createNotificationMsg($eventId1, 1, MediaType::Text, "E1 Text", NotificationMsgStatus::New);
        $msgId1_1 = $this->otificationMsgRepository->create($msg1_1);

        $msg1_2 = $this->createNotificationMsg($eventId1, 2, MediaType::Image, "", NotificationMsgStatus::New);
        $this->otificationMsgRepository->create($msg1_2);

        // 2. Create Event 2 with 1 notification (Control)
        $event2 = $this->createTestEvent('Event 2', new DateTime('+1 day'));
        $eventId2 = $this->eventRepository->create($event2);

        $msg2_1 = $this->createNotificationMsg($eventId2, 1, MediaType::Text, "E2 Text", NotificationMsgStatus::New);
        $msgId2 = $this->otificationMsgRepository->create($msg2_1);

        // 3. Approve (Schedule) Event 1
        $this->eventManager->approveNotification($msgId1_1);

        // 4. Verify Event 1 notifications are Scheduled
        $msgs1 = $this->database->table('notification_msg')
            ->where('event_id', $eventId1)
            ->fetchAll();

        foreach ($msgs1 as $row) {
            $this->assertEquals(NotificationMsgStatus::Scheduled->value, $row->status);

            // Assert Attempt created
            $attempt = $this->database->table('notification_attempt')
                ->where('notification_msg_id', $row->id)
                ->fetch();

            if ($row->notification_type === NotificationType::Main->value && $row->media_type === MediaType::Text->value) {
                $this->assertNotNull($attempt, "Attempt should be created for notification {$row->id}, type: {$row->notification_type}, media: {$row->media_type}");
                $this->assertEquals($row->send_at, $attempt->send_at, "Attempt send date should match message send date");

            } else {
                $this->assertNull($attempt, "Attempt should not be created for notification {$row->id}, type: {$row->notification_type}, media: {$row->media_type}");
            }
        }

        // 5. Verify Event 2 notification is still New
        $row2 = $this->database->table('notification_msg')->get($msgId2);
        $this->assertEquals(NotificationMsgStatus::New ->value, $row2->status);
    }
}
