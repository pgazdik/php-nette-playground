<?php
namespace Tests\Service;

use App\Common\Maybe;
use DateTime;
use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Model\Entity\Event\NotificationType;
use App\Service\NotificationAttemptRepository;
use App\Service\NotificationMsgRepository;
use App\Service\NotificationManager;
use App\Service\SmsGwService;
use Tests\Service\SmsGwMockService;

class NotificationManagerTest extends EventDbTestCase
{
    private NotificationManager $notificationManager;
    private NotificationMsgRepository $notificationMsgRepository;
    private NotificationAttemptRepository $notificationAttemptRepository;
    private SmsGwService $smsGwService;

    public function setUp(): void
    {
        parent::setUp();

        $this->dropAllTables();
        $this->createEventTable();
        $this->createNotificationTable();
        $this->createNotificationAttemptTable();

        $this->notificationManager = $this->container->getByType(NotificationManager::class);
        $this->notificationMsgRepository = $this->container->getByType(NotificationMsgRepository::class);
        $this->notificationAttemptRepository = $this->container->getByType(NotificationAttemptRepository::class);
        $this->smsGwService = $this->container->getByType(SmsGwService::class);

        $this->assertInstanceOf(SmsGwMockService::class, $this->smsGwService);
}

    public function testSendEligibleNotifications()
    {
        $TEXT = "HELLO";

        // 1. Prepare Data
        // Create Event
        $event = $this->createTestEvent('Test Patient', new DateTime('+1 day'));
        $eventId = $this->eventRepository->create($event);

        // Create Notification Msg
        $msg = $this->createNotificationMsg($eventId, 1, MediaType::Text, $TEXT, NotificationMsgStatus::Scheduled);
        $msgId = $this->notificationMsgRepository->create($msg);

        // Create Scheduled Attempt (Eligible for sending)
        $attempt = new NotificationAttempt(
            id: 0, // auto increment
            notificationMsgId: $msgId,
            attemptNo: 1,
            sendAt: new DateTime('-1 hour'), // In the past
            status: NotificationAttemptStatus::Scheduled,
            sendingError: null
        );
        $attemptId = $this->notificationAttemptRepository->create($attempt);

        // 2. Setup Mock
        $mockCallCount = 0;

        $this->smsGwService->handler = function($urlPath, $postData) use (&$mockCallCount, $TEXT) {
            $mockCallCount++;
            $this->assertEquals(NotificationManager::$MMS_PATH, $urlPath);

            $data = json_decode($postData, true);
            $this->assertContains(self::$PHONE_NUMBER, $data['to']); // Phone number from createTestEvent
            $this->assertEquals($TEXT, $data['text']);
            
            // Return success
            return Maybe::success([(object)['id' => 12345, 'status' => 'queued']]);
        };

        // 3. Execute
        $this->notificationManager->sendEligibleNotifications();

        // 4. Verify
        $this->assertEquals(1, $mockCallCount, "SMS Gateway should have been called once");

        // Verify Attempt Status updated to Sent
        $updatedAttempt = $this->fetchAttempt($attemptId);
        $this->assertEquals(NotificationAttemptStatus::Sent->value, $updatedAttempt->status);
        $this->assertEquals(12345, $updatedAttempt->gw_id);
        $this->assertEquals('queued', $updatedAttempt->gw_send_status);
    }

    private function fetchAttempt(int $id)
    {
        return $this->database->table('notification_attempt')->get($id);
    }
}
