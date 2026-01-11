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
    private SmsGwService $smsGwService;

    public function setUp(): void
    {
        parent::setUp();

        $this->initDb();

        $this->notificationManager = $this->container->getByType(NotificationManager::class);
        $this->smsGwService = $this->container->getByType(SmsGwService::class);

        $this->assertInstanceOf(SmsGwMockService::class, $this->smsGwService);
    }

    public function testSendEligibleNotifications()
    {
        $TEXT = "HELLO";
        // 1. Prepare Data
        $msg = $this->createTestEventWithMsg('Test Patient', $TEXT, NotificationMsgStatus::Scheduled, new DateTime('-1 hour'));
        $attempt = $this->createTestAttempt($msg, NotificationAttemptStatus::Scheduled);

        // 2. Setup Mock
        $mockCallCount = 0;
        $this->smsGwService->handler = function ($urlPath, $postData) use (&$mockCallCount, $TEXT) {
            $mockCallCount++;
            $this->assertEquals(NotificationManager::$MMS_PATH, $urlPath);

            $data = json_decode($postData, true);
            $this->assertContains(self::$PHONE_NUMBER, $data['to']);
            $this->assertEquals($TEXT, $data['text']);

            return Maybe::success([(object) ['id' => 12345, 'status' => 'queued']]);
        };

        // 3. Execute
        $this->notificationManager->sendEligibleNotifications();

        // 4. Verify
        $this->assertEquals(1, $mockCallCount, "SMS Gateway should have been called once");

        $updatedAttempt = $this->notificationAttemptRepository->getById($attempt->id);
        $this->assertEquals(NotificationAttemptStatus::Sent, $updatedAttempt->status);
        $this->assertEquals(12345, $updatedAttempt->gwId);
        $this->assertEquals('queued', $updatedAttempt->gwSendStatus);
        $this->assertNotNull($updatedAttempt->sentAt);
    }

    public function testSendEligibleNotificationsFailureReschedules()
    {
        $ERROR_MSG = "Simulated Gateway Error";
        // 1. Prepare Data
        $msg = $this->createTestEventWithMsg('Failure Patient', 'FAIL ME', NotificationMsgStatus::Scheduled, new DateTime('-1 second'));
        $firstAttempt = $this->createTestAttempt($msg, NotificationAttemptStatus::Scheduled);

        // 2. Setup Mock
        $this->smsGwService->handler = fn() => Maybe::error($ERROR_MSG);

        // 3. Execute
        $this->notificationManager->sendEligibleNotifications();

        // 4. Verify
        $attempts = $this->notificationAttemptRepository->listByMsgId($msg->id);
        $this->assertCount(2, $attempts);

        $this->assertEquals(NotificationAttemptStatus::Failed, $attempts[0]->status);
        $this->assertEquals($ERROR_MSG, $attempts[0]->sendingError);

        $this->assertEquals(NotificationAttemptStatus::Scheduled, $attempts[1]->status);
        $this->assertEquals(2, $attempts[1]->attemptNo);
        $this->assertMaxTimeDiffInSeconds(NotificationAttempt::computeDelay($firstAttempt), $attempts[1]->scheduledAt, 1);
    }

    public function testCheckStatusOfSentNotifications_SchedulesNextMessage()
    {
        $GW_ID = 12345;

        // 1. Prepare Data
        $msg1 = $this->createTestEventWithMsg('Patient Check', 'Msg 1', NotificationMsgStatus::Scheduled, new DateTime('-1 hour'));
        $msg2 = $this->createNotificationMsg($msg1->eventId, 2, MediaType::Image, "", NotificationMsgStatus::Scheduled, new DateTime('-1 hour'));
        $this->notificationMsgRepository->insert($msg2);

        $attempt1 = $this->createTestAttempt($msg1, NotificationAttemptStatus::Sent, null, $GW_ID);

        // 2. Setup Mock
        $this->smsGwService->handler = function ($urlPath) use ($GW_ID): Maybe {
            $this->assertStringContainsString("id_from=$GW_ID", $urlPath);
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
        $attempt1 = $this->notificationAttemptRepository->getById($attempt1->id);
        $this->assertEquals(NotificationAttemptStatus::Delivered, $attempt1->status);
        $this->assertEquals(NotificationMsgStatus::Delivered, $attempt1->msg->status);

        $attempts2 = $this->notificationAttemptRepository->listByMsgId($msg2->id);
        $this->assertCount(1, $attempts2);
        $this->assertEquals(NotificationAttemptStatus::Scheduled, $attempts2[0]->status);
    }

    public function testCheckStatusOfSentNotifications_FailureReschedulesAttempt()
    {
        $GW_ID = 12345;

        // 1. Prepare Data
        $msg1 = $this->createTestEventWithMsg('Patient Check', 'Msg 1', NotificationMsgStatus::Scheduled, new DateTime('-1 hour'));
        $this->createTestAttempt($msg1, NotificationAttemptStatus::Sent, null, $GW_ID);

        // 2. Setup Mock
        $this->smsGwService->handler = fn() => Maybe::success([
            (object) [
                'status' => 'sending_error',
                'error_code' => 500,
                'sending_date' => '2023-01-01 10:00:00',
                'delivery_date' => null,
            ]
        ]);

        // 3. Execute
        $this->notificationManager->checkStatusOfSentNotifications();

        // 4. Verify
        $attempts = $this->notificationAttemptRepository->listByMsgId($msg1->id);
        $this->assertCount(2, $attempts);

        $this->assertEquals(NotificationAttemptStatus::Failed, $attempts[0]->status);
        $this->assertEquals(500, $attempts[0]->gwErrorCode);

        $this->assertEquals(NotificationAttemptStatus::Scheduled, $attempts[1]->status);
        $this->assertEquals(2, $attempts[1]->attemptNo);
    }

    public function testCheckMessage_ResourceNotFound()
    {
        // 1. Prepare Data
        $msg1 = $this->createTestEventWithMsg('Patient Check 2', 'Msg 2', NotificationMsgStatus::Scheduled, new DateTime('-1 hour'));
        $attempt = $this->createTestAttempt($msg1, NotificationAttemptStatus::Sent, null, 2357);

        // 2. Setup Mock
        $this->smsGwService->handler = fn():Maybe => Maybe::success((object) ['message' => "Resource(s) not found"]);

        // 3. Execute
        $this->notificationManager->checkStatusOfSentNotifications();

        // 4. Verify
        $updatedAttempt = $this->notificationAttemptRepository->getById($attempt->id);
        $this->assertEquals(NotificationAttemptStatus::NotFound, $updatedAttempt->status);
        $this->assertNull($updatedAttempt->checkError, "checkError should be null");
    }

    public function testCheckMessage_CheckFailed_NonsensicalResponse()
    {
        // 1. Prepare Data
        $msg1 = $this->createTestEventWithMsg('Patient Check 2', 'Msg 2', NotificationMsgStatus::Scheduled, new DateTime('-1 hour'));
        $attempt = $this->createTestAttempt($msg1, NotificationAttemptStatus::Sent, null, 2357);

        // 2. Setup Mock
        $this->smsGwService->handler = fn():Maybe => Maybe::success("WTF");

        // 3. Execute
        $this->notificationManager->checkStatusOfSentNotifications();

        // 4. Verify
        $updatedAttempt = $this->notificationAttemptRepository->getById($attempt->id);
        $this->assertEquals(NotificationAttemptStatus::CheckError, $updatedAttempt->status);
        $this->assertNotNull($updatedAttempt->checkError, "checkError should not be null");
        $this->assertStringContainsString('Unexpected response: "WTF"', $updatedAttempt->checkError);
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
