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
        $hourAgo = new DateTime('-1 hour'); // In the past
        // 1. Prepare Data
        // Create Event
        $event = $this->createTestEvent('Test Patient', new DateTime('+1 day'));
        $eventId = $this->eventRepository->create($event);

        // Create Notification Msg
        $msg = $this->createNotificationMsg($eventId, 1, MediaType::Text, $TEXT, NotificationMsgStatus::Scheduled, $hourAgo);
        $this->notificationMsgRepository->create($msg);

        // Create Scheduled Attempt (Eligible for sending)
        $attempt = NotificationAttempt::createFirstAttempt($msg);
        $this->notificationAttemptRepository->create($attempt);

        // 2. Setup Mock
        $mockCallCount = 0;

        $this->smsGwService->handler = function ($urlPath, $postData) use (&$mockCallCount, $TEXT) {
            $mockCallCount++;
            $this->assertEquals(NotificationManager::$MMS_PATH, $urlPath);

            $data = json_decode($postData, true);
            $this->assertContains(self::$PHONE_NUMBER, $data['to']); // Phone number from createTestEvent
            $this->assertEquals($TEXT, $data['text']);

            // Return success
            return Maybe::success([(object) ['id' => 12345, 'status' => 'queued']]);
        };

        // 3. Execute
        $this->notificationManager->sendEligibleNotifications();

        // 4. Verify
        $this->assertEquals(1, $mockCallCount, "SMS Gateway should have been called once");

        // Verify Attempt Status updated to Sent
        $updatedAttempt = $this->notificationAttemptRepository->getById($attempt->id);
        $this->assertEquals(NotificationAttemptStatus::Sent, $updatedAttempt->status);
        $this->assertEquals(12345, $updatedAttempt->gwId);
        $this->assertEquals('queued', $updatedAttempt->gwSendStatus);
    }

    public function testSendEligibleNotificationsFailureReschedules()
    {
        $TEXT = "FAIL ME";
        $ERROR_MSG = "Simulated Gateway Error";

        // 1. Prepare Data
        $event = $this->createTestEvent('Failure Patient', new DateTime('+2 days'));
        $eventId = $this->eventRepository->create($event);

        $msg = $this->createNotificationMsg($eventId, 1, MediaType::Text, $TEXT, NotificationMsgStatus::Scheduled, new DateTime('-1 second'));
        $this->notificationMsgRepository->create($msg);

        // Create Attempt 1
        $firstAttempt = NotificationAttempt::createFirstAttempt(msg: $msg);
        $this->notificationAttemptRepository->create($firstAttempt);

        // 2. Setup Mock to Fail
        $mockCalled = false;
        $this->smsGwService->handler = function ($urlPath, $postData) use (&$mockCalled, $ERROR_MSG) {
            $mockCalled = true;
            return Maybe::error($ERROR_MSG);
        };

        // 3. Execute
        $this->notificationManager->sendEligibleNotifications();
        $this->assertTrue($mockCalled, "Mock should have been called");

        // Verify first Attempt Updated and Second Scheduled
        $attempts = $this->notificationAttemptRepository->listByMsgId($msg->id);
        $this->assertCount(2, $attempts, "Should have 2 attempts now");

        $firstAttemptFresh = $attempts[0];
        $this->assertEquals(NotificationAttemptStatus::Failed, $firstAttemptFresh->status);
        $this->assertEquals($ERROR_MSG, $firstAttemptFresh->sendingError);

        $nextAttempt = $attempts[1];
        $this->assertEquals(2, $nextAttempt->attemptNo);
        $this->assertEquals(NotificationAttemptStatus::Scheduled, $nextAttempt->status);

        $this->assertMaxTimeDiffInSeconds(NotificationAttempt::computeDelay($firstAttempt), $nextAttempt->sendAt, 1);
    }

    public function testCheckStatusOfSentNotifications_SchedulesNextMessage()
    {
        $GW_ID = 12345;
        $hourAgo = new DateTime('-1 hour');

        // 1. Prepare Data
        $event = $this->createTestEvent('Patient Check', new DateTime('+2 days'));
        $eventId = $this->eventRepository->create($event);

        // Msg 1 (Text) - Sent
        $msg1 = $this->createNotificationMsg($eventId, 1, MediaType::Text, "Msg 1", NotificationMsgStatus::Scheduled, $hourAgo);
        $this->notificationMsgRepository->create($msg1);

        // Msg 2 (Image) - Waiting (Scheduled but no attempt yet)
        $msg2 = $this->createNotificationMsg($eventId, 2, MediaType::Image, "", NotificationMsgStatus::Scheduled, $hourAgo);
        $this->notificationMsgRepository->create($msg2);

        // Create Attempt for Msg 1 (Sent status)
        $attempt1 = NotificationAttempt::createFirstAttempt(msg: $msg1);
        $attempt1->status = NotificationAttemptStatus::Sent;
        $attempt1->gwId = $GW_ID;

        $this->notificationAttemptRepository->create($attempt1);

        // 2. Setup Mock for Checking
        // We expect a call to check status
        $mockCalled = false;
        $this->smsGwService->handler = function ($urlPath, $postData) use (&$mockCalled, $GW_ID): Maybe {
            $mockCalled = true;
            // Expecting 'sent?id_from=12345&id_to=12345'
            $this->assertStringContainsString("sent?", $urlPath);
            $this->assertStringContainsString("id_from=$GW_ID", $urlPath);

            // Return Delivered response
            return Maybe::success([
                (object) [
                    'status' => 'delivery_ok',
                    'error_code' => null,
                    'sending_date' => '2023-01-01 10:00:00',
                    'delivery_date' => '2023-01-01 10:01:00'
                ]
            ]);
        };

        // 3. Execute
        $this->notificationManager->checkStatusOfSentNotifications();

        // 4. Verify
        $this->assertTrue($mockCalled, "Mock check should have been called");

        // Verify Attempt 1 is Delivered
        $updatedAttempt1 = $this->notificationAttemptRepository->getById($attempt1->id);
        $this->assertEquals(NotificationAttemptStatus::Delivered, $updatedAttempt1->status);
        $this->assertNotNull($updatedAttempt1->gwDeliveryDate);

        // Verify Message 1 is Delivered
        $updatedMsg1 = $this->notificationMsgRepository->getById($msg1->id);
        $this->assertEquals(NotificationMsgStatus::Delivered, $updatedMsg1->status);

        // Verify Attempt for Msg 2 is Created (Scheduled)
        $attempts2 = $this->notificationAttemptRepository->listByMsgId($msg2->id);
        $this->assertCount(1, $attempts2, "Should have created an attempt for Msg 2");

        $this->assertEquals(NotificationAttemptStatus::Scheduled, $attempts2[0]->status);
    }

    public function testCheckStatusOfSentNotifications_FailureReschedulesAttempt()
    {
        $GW_ID = 12345;
        $hourAgo = new DateTime('-1 hour');

        // 1. Prepare Data
        $event = $this->createTestEvent('Patient Check', new DateTime('+2 days'));
        $eventId = $this->eventRepository->create($event);

        // Msg 1 (Text)
        $msg1 = $this->createNotificationMsg($eventId, 1, MediaType::Text, "Msg 1", NotificationMsgStatus::Scheduled, $hourAgo);
        $this->notificationMsgRepository->create($msg1);

        // Create Attempt 1 for Msg 1 (Sent status)
        $attempt1 = NotificationAttempt::createFirstAttempt(msg: $msg1);
        $attempt1->status = NotificationAttemptStatus::Sent;
        $attempt1->gwId = $GW_ID;
        $this->notificationAttemptRepository->create($attempt1);

        // 2. Setup Mock for Checking to return 'sending_error'
        $mockCalled = false;
        $this->smsGwService->handler = function ($urlPath, $postData) use (&$mockCalled, $GW_ID): Maybe {
            $mockCalled = true;

            // Expecting 'sent?id_from=12345&id_to=12345'
            $this->assertStringContainsString("sent?", $urlPath);
            $this->assertStringContainsString("id_from=$GW_ID", $urlPath);

            // Return 'sending_error' response
            return Maybe::success([
                (object) [
                    'status' => 'sending_error',
                    'error_code' => 500,
                    'sending_date' => '2023-01-01 10:00:00',
                    'delivery_date' => null,
                ]
            ]);
        };

        // 3. Execute
        $this->notificationManager->checkStatusOfSentNotifications();

        // 4. Verify
        $this->assertTrue($mockCalled, "Mock check should have been called");

        // // Verify Attempts
        $attempts = $this->notificationAttemptRepository->listByMsgId($msg1->id);
        $this->assertCount(2, $attempts, "Should have 2 attempts now (1 failed, 1 rescheduled)");

        $failedAttempt = $attempts[0];
        $this->assertEquals(NotificationAttemptStatus::Failed, $failedAttempt->status);
        $this->assertEquals(500, $failedAttempt->gwErrorCode);

        $nextAttempt = $attempts[1];
        $this->assertEquals(2, $nextAttempt->attemptNo);
        $this->assertEquals(NotificationAttemptStatus::Scheduled, $nextAttempt->status);
    }

    //
    // Helpers
    //

    private function assertMaxTimeDiffInSeconds(DateTime $expected, DateTime $actual, int $maxDiffSeconds, ?string $msg = null): void
    {
        $diffInSeconds = abs($expected->getTimestamp() - $actual->getTimestamp());
        if (!$msg)
            $msg = 'Time difference should be max ' . $maxDiffSeconds . ' seconds';
        $this->assertLessThanOrEqual($maxDiffSeconds, $diffInSeconds, $msg);
    }
}
