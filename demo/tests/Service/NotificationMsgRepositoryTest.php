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
        $this->eventRepository->insert($event);

        // 2. Create Notifications

        // To Approve: New / Text
        $targetMsg = $this->createTextNotificationMsg($event->id, 1, 'Target Message', NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($targetMsg);

        // Ignored: New / Image
        $imageMsg = $this->createImageNotificationMsg($event->id, 2, '', NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($imageMsg);

        // Ignored: Scheduled / Text
        $scheduledMsg = $this->createTextNotificationMsg($event->id, 1, 'Scheduled Message', NotificationMsgStatus::Scheduled);
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
        $this->eventRepository->insert($event);

        $msg = $this->createTextNotificationMsg($event->id, 1, 'Original Text', NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($msg);

        // 2. Update Text
        $newText = 'Updated Text Content';
        $this->notificationMsgRepository->updateText($msg->id, $newText);

        // 3. Verify
        $updatedMsg = $this->notificationMsgRepository->getById($msg->id);
        $this->assertEquals($newText, $updatedMsg->text);
    }

    public function test_FindNextMessages()
    {
        // 1. Create Event
        $event = $this->createTestEvent('Next Messages Test');
        $this->eventRepository->insert($event);

        // 2. Create Notifications
        $msg1 = $this->createTextNotificationMsg($event->id, 1, "Msg 1", NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($msg1);

        $msg2a = $this->createTextNotificationMsg($event->id, 2, "Msg 2a", NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($msg2a);

        $msg2b = $this->createImageNotificationMsg($event->id, 2, "", NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($msg2b);

        $msg3 = $this->createTextNotificationMsg($event->id, 3, "Msg 3", NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($msg3);

        // 3. Find next messages for Msg 1 (should return 2a and 2b)
        $nextMsgs = $this->notificationMsgRepository->findNextMessages($msg1);

        $this->assertCount(2, $nextMsgs);
        $ids = array_map(fn($m) => $m->id, $nextMsgs);
        $this->assertContains($msg2a->id, $ids);
        $this->assertContains($msg2b->id, $ids);
        $this->assertNotContains($msg1->id, $ids);
        $this->assertNotContains($msg3->id, $ids);
    }

    public function test_InsertWithFilePath()
    {
        $event = $this->createTestEvent('File Path Test');
        $this->eventRepository->insert($event);

        $filePath = 'path/to/file.jpg';
        $msg = $this->createImageNotificationMsg($event->id, 2, $filePath, NotificationMsgStatus::Draft, new DateTime());
        $this->notificationMsgRepository->insert($msg);

        $fetched = $this->notificationMsgRepository->getById($msg->id);
        $this->assertEquals($filePath, $fetched->filePath);
    }
}
