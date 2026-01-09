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
    private NotificationMsgRepository $otificationMsgRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->dropAllTables();
        $this->createEventTable();
        $this->createNotificationTable();
        $this->createNotificationAttemptTable();

        $this->otificationMsgRepository = $this->container->getByType(NotificationMsgRepository::class);
    }

    public function test_GetToApprove_Returns_OnlyNewTextMessages()
    {
        // 1. Create Event
        $event = $this->createTestEvent('Test Patient', new DateTime('+1 day'));
        $eventId = $this->eventRepository->create($event);

        // 2. Create Notifications

        // To Approve: New / Text
        $targetMsg = $this->createNotificationMsg($eventId, 1, MediaType::Text, 'Target Message', NotificationMsgStatus::New);
        $this->otificationMsgRepository->create($targetMsg);

        // Ignored: New / Image
        $imageMsg = $this->createNotificationMsg($eventId, 2, MediaType::Image, '', NotificationMsgStatus::New);
        $this->otificationMsgRepository->create($imageMsg);

        // Ignored: Scheduled / Text
        $scheduledMsg = $this->createNotificationMsg($eventId, 1, MediaType::Text, 'Scheduled Message', NotificationMsgStatus::Scheduled);
        $this->otificationMsgRepository->create($scheduledMsg);

        // 3. Test getToApprove
        $results = $this->otificationMsgRepository->getToApprove(10, 0);

        $this->assertCount(1, $results);
        $this->assertEquals('Target Message', $results[0]->text);
        $this->assertEquals(MediaType::Text, $results[0]->mediaType);
        $this->assertEquals(NotificationMsgStatus::New , $results[0]->status);
    }

    public function test_UpdateText()
    {
        // 1. Create Event & Notification
        $event = $this->createTestEvent('Updater', new DateTime('+1 day'));
        $eventId = $this->eventRepository->create($event);

        $msg = $this->createNotificationMsg($eventId, 1, MediaType::Text, 'Original Text', NotificationMsgStatus::New);
        $msgId = $this->otificationMsgRepository->create($msg);

        // 2. Update Text
        $newText = 'Updated Text Content';
        $this->otificationMsgRepository->updateText($msgId, $newText);

        // 3. Verify
        $updatedMsg = $this->otificationMsgRepository->getById($msgId);
        $this->assertEquals($newText, $updatedMsg->text);
    }
}
