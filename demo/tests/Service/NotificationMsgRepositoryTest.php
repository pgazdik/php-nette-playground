<?php
namespace Tests\Service;

use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Model\Entity\Event\NotificationType;
use App\Service\NotificationMsgRepository;
use DateTime;

class NotificationMsgRepositoryTest extends EventDbTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_GetToApprove_Returns_OnlyNewTextMessages()
    {
        // 1. Create Event
        $event = $this->createTestEvent('Test Patient', new DateTime('+1 day'));
        $eventId = $this->eventRepository->insert($event);

        // 2. Create Notifications

        // To Approve: New / Text
        $targetMsg = $this->createNotificationMsg($eventId, 1, MediaType::Text, 'Target Message', NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($targetMsg);

        // Ignored: New / Image
        $imageMsg = $this->createNotificationMsg($eventId, 2, MediaType::Image, '', NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($imageMsg);

        // Ignored: Scheduled / Text
        $scheduledMsg = $this->createNotificationMsg($eventId, 1, MediaType::Text, 'Scheduled Message', NotificationMsgStatus::Scheduled);
        $this->notificationMsgRepository->insert($scheduledMsg);

        // 3. Test getToApprove
        $results = $this->notificationMsgRepository->getToApprove(10, 0);

        $this->assertCount(1, $results);
        $this->assertEquals('Target Message', $results[0]->text);
        $this->assertEquals(MediaType::Text, $results[0]->mediaType);
        $this->assertEquals(NotificationMsgStatus::Draft , $results[0]->status);
    }

    public function test_UpdateText()
    {
        // 1. Create Event & Notification
        $event = $this->createTestEvent('Updater', new DateTime('+1 day'));
        $eventId = $this->eventRepository->insert($event);

        $msg = $this->createNotificationMsg($eventId, 1, MediaType::Text, 'Original Text', NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($msg);

        // 2. Update Text
        $newText = 'Updated Text Content';
        $this->notificationMsgRepository->updateText($msg->id, $newText);

        // 3. Verify
        $updatedMsg = $this->notificationMsgRepository->getById($msg->id);
        $this->assertEquals($newText, $updatedMsg->text);
    }
}
