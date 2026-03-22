<?php
namespace Tests\Service;

use App\Common\Maybe;
use DateTime;
use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Service\NotificationManager;
use App\Service\SmsGwService;
use Tests\Service\SmsGwMockService;
use Tracy\Debugger;

class NotificationManagerTest extends EventDbTestCase
{
    private NotificationManager $notificationManager;
    private SmsGwService $smsGwService;

    public function setUp(): void
    {
        parent::setUp();

        $this->notificationManager = $this->container->getByType(NotificationManager::class);
        $this->smsGwService = $this->container->getByType(SmsGwService::class);

        $this->assertInstanceOf(SmsGwMockService::class, $this->smsGwService);
    }

    // #################################
    // Approve / Withdraw
    // #################################

    public function test_ApproveNotification()
    {
        // 1. Create Event 1 with 2 notifications
        $event1 = $this->createTestEvent('Event 1', new DateTime('+1 day'));
        $this->eventRepository->insert($event1);

        $msg1_1 = $this->createTextNotificationMsg($event1->id, 1, "E1 Text", NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($msg1_1);

        $msg1_2 = $this->createImageNotificationMsg($event1->id, 2, "", NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($msg1_2);

        // 2. Create Event 2 with 1 notification (Control)
        $event2 = $this->createTestEvent('Event 2', new DateTime('+1 day'));
        $this->eventRepository->insert($event2);

        $msg2_1 = $this->createTextNotificationMsg($event2->id, 1, "E2 Text", NotificationMsgStatus::Draft);
        $this->notificationMsgRepository->insert($msg2_1);

        // 3. Approve (Schedule) Event 1
        $this->notificationManager->approveNotification($msg1_1->id);

        // 4. Verify Event 1 notifications

        // Msg 1 should be Scheduled
        $row1_1 = $this->database->table('notification_msg')->get($msg1_1->id);
        $this->assertEquals(NotificationMsgStatus::Scheduled->value, $row1_1->status);

        // Msg 2 should still be New (sequential processing)
        $row1_2 = $this->database->table('notification_msg')->get($msg1_2->id);
        $this->assertEquals(NotificationMsgStatus::Draft->value, $row1_2->status);

        // Verify NO attempts created
        $attemptsCount = $this->database->table('notification_attempt')->count('*');
        $this->assertEquals(0, $attemptsCount, "No attempts should be created on approval");

        // 5. Verify Event 2 notification is still New
        $row2 = $this->database->table('notification_msg')->get($msg2_1->id);
        $this->assertEquals(NotificationMsgStatus::Draft->value, $row2->status);
    }

    public function test_WithdrawNotification()
    {
        // 1. Setup Scheduled message
        $event = $this->createTestEvent('Withdraw Test');
        $this->eventRepository->insert($event);
        $msg = $this->createTextNotificationMsg($event->id, 1, "Withdraw me", NotificationMsgStatus::Scheduled);
        $this->notificationMsgRepository->insert($msg);

        // 2. Withdraw
        $this->notificationManager->withdrawNotification($msg->id);

        // 3. Verify
        $updatedMsg = $this->notificationMsgRepository->getById($msg->id);
        $this->assertEquals(NotificationMsgStatus::Draft, $updatedMsg->status);
    }

    // #################################
    // Sending
    // #################################

    public function testSend_SendOk()
    {
        $TEXT = "HELLO";
        // 1. Prepare Data
        $msg = $this->createTestEventWithMsg('Test Patient', $TEXT, NotificationMsgStatus::Scheduled, new DateTime('-1 hour'));

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

        $attempts = $this->notificationAttemptRepository->listByMsgId($msg->id);
        $this->assertCount(1, $attempts);
        $updatedAttempt = $attempts[0];

        $this->assertEquals(NotificationAttemptStatus::Sent, $updatedAttempt->status);
        $this->assertEquals(12345, $updatedAttempt->gwId);
        $this->assertEquals('queued', $updatedAttempt->gwSendStatus);
        $this->assertNotNull($updatedAttempt->sentAt);
        $this->assertEquals($updatedAttempt->checkAt, $updatedAttempt->sentAt);

        $updatedMsg = $this->notificationMsgRepository->getById($msg->id);
        $this->assertEquals(NotificationMsgStatus::Sent, $updatedMsg->status);
    }

    public function testSend_SendFails_GwResponseGarbage_Reschedules()
    {
        $ERROR_MSG = "Simulated Gateway Error";
        // 1. Prepare Data
        $msg = $this->createTestEventWithMsg('Failure Patient', 'FAIL ME', NotificationMsgStatus::Scheduled, new DateTime('-1 second'));

        // 2. Setup Mock
        $this->smsGwService->handler = fn() => Maybe::error($ERROR_MSG);

        // 3. Execute
        $this->notificationManager->sendEligibleNotifications();

        // 4. Verify
        $attempts = $this->notificationAttemptRepository->listByMsgId($msg->id);
        $this->assertCount(1, $attempts);

        $this->assertEquals(NotificationAttemptStatus::Failed, $attempts[0]->status);
        $this->assertEquals($ERROR_MSG, $attempts[0]->sendingError);

        $updatedMsg = $this->notificationMsgRepository->getById($msg->id);
        $this->assertEquals(NotificationMsgStatus::Scheduled, $updatedMsg->status);

        // Check rescheduled future time
        $this->assertGreaterThan(time(), $updatedMsg->scheduledAt->getTimestamp());
    }

    public function testSend_SendImageOk()
    {
        $FILE_PATH = "pepper/dr-pepper.jpg";
        // 1. Prepare Data
        $event = $this->createTestEvent('Image Patient');
        $this->eventRepository->insert($event);

        $msg = $this->createImageNotificationMsg(
            $event->id,
            2,
            $FILE_PATH,
            NotificationMsgStatus::Scheduled,
            new DateTime('-1 hour')
        );
        $this->notificationMsgRepository->insert($msg);

        // 2. Setup Mock
        $mockCallCount = 0;
        $this->smsGwService->handler = function ($urlPath, $postData) use (&$mockCallCount) {
            $mockCallCount++;
            $this->assertEquals(NotificationManager::$MMS_PATH, $urlPath);

            $data = json_decode($postData, true);
            $this->assertCount(1, $data['attachments']);
            $this->assertEquals('image/jpeg', $data['attachments'][0]['content_type']);
            $this->assertNotEmpty($data['attachments'][0]['content']);

            return Maybe::success([(object) ['id' => 54321, 'status' => 'queued']]);
        };

        // 3. Execute
        $this->notificationManager->sendEligibleNotifications();

        // 4. Verify
        $this->assertEquals(1, $mockCallCount);
        $updatedMsg = $this->notificationMsgRepository->getById($msg->id);
        $this->assertEquals(NotificationMsgStatus::Sent, $updatedMsg->status);
    }

    // #################################
    // Checking Status
    // #################################

    public function testCheckStatusOfSentNotifications_SchedulesNextMessage()
    {
        $GW_ID = 12345;

        // 1. Prepare Data
        $msg1 = $this->createTestEventWithMsg('Patient Check', 'Msg 1', NotificationMsgStatus::Sent, new DateTime('-1 hour'));
        // Create second message as NEW (so it gets scheduled)
        $msg2 = $this->createImageNotificationMsg($msg1->eventId, 2, "", NotificationMsgStatus::Draft, new DateTime('-1 hour'));
        $this->notificationMsgRepository->insert($msg2);

        $attempt1 = $this->createTestAttempt($msg1, NotificationAttemptStatus::Sent, $GW_ID);

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
        $updatedAttempt = $this->notificationAttemptRepository->getById($attempt1->id);
        $this->assertMaxTimeDiffInSeconds($attempt1->checkAt, $updatedAttempt->checkAt, 1);
        $this->assertEquals(NotificationAttemptStatus::Delivered, $updatedAttempt->status);
        $this->assertEquals(NotificationMsgStatus::Delivered, $this->notificationMsgRepository->getById($msg1->id)->status);

        $updatedMsg2 = $this->notificationMsgRepository->getById($msg2->id);
        $this->assertEquals(NotificationMsgStatus::Scheduled, $updatedMsg2->status);
    }

    public function testCheckStatusOfSentNotifications_SchedulesMultipleNextMessages()
    {
        $GW_ID = 12345;

        // 1. Prepare Data
        $msg1 = $this->createTestEventWithMsg('Parallel Check', 'Msg 1', NotificationMsgStatus::Sent, new DateTime('-1 hour'));

        // Create TWO messages with index 2
        $msg2a = $this->createTextNotificationMsg($msg1->eventId, 2, "Msg 2a", NotificationMsgStatus::Draft, new DateTime('-1 hour'));
        $this->notificationMsgRepository->insert($msg2a);

        $msg2b = $this->createTextNotificationMsg($msg1->eventId, 2, "Msg 2b", NotificationMsgStatus::Draft, new DateTime('-1 hour'));
        $this->notificationMsgRepository->insert($msg2b);

        $this->createTestAttempt($msg1, NotificationAttemptStatus::Sent, $GW_ID);

        // 2. Setup Mock
        $this->smsGwService->handler = function ($urlPath) use ($GW_ID): Maybe {
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
        $this->assertEquals(NotificationMsgStatus::Delivered, $this->notificationMsgRepository->getById($msg1->id)->status);

        // BOTH should be Scheduled
        $this->assertEquals(NotificationMsgStatus::Scheduled, $this->notificationMsgRepository->getById($msg2a->id)->status);
        $this->assertEquals(NotificationMsgStatus::Scheduled, $this->notificationMsgRepository->getById($msg2b->id)->status);
    }

    public function testCheckStatus_GwSendFailure_ReschedulesMsg()
    {
        $GW_ID = 12345;

        // 1. Prepare Data
        $msg = $this->createTestEventWithMsg('Patient Check', 'Msg 1', NotificationMsgStatus::Sent, new DateTime('-1 hour'));
        $this->createTestAttempt($msg, NotificationAttemptStatus::Sent, $GW_ID);

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
        $attempts = $this->notificationAttemptRepository->listByMsgId($msg->id);
        $this->assertCount(1, $attempts);

        $this->assertEquals(NotificationAttemptStatus::Failed, $attempts[0]->status);
        $this->assertEquals(500, $attempts[0]->gwErrorCode);

        $updatedMsg = $this->notificationMsgRepository->getById($msg->id);
        $this->assertEquals(NotificationMsgStatus::Scheduled, $updatedMsg->status);
        $this->assertGreaterThan(time(), $updatedMsg->scheduledAt->getTimestamp());
    }

    public function testCheck_ResourceNotFound()
    {
        // 1. Prepare Data
        $msg1 = $this->createTestEventWithMsg('Patient Check 2', 'Msg 2', NotificationMsgStatus::Sent, new DateTime('-1 hour'));
        $attempt = $this->createTestAttempt($msg1, NotificationAttemptStatus::Sent, 2357);

        // 2. Setup Mock
        $this->smsGwService->handler = fn(): Maybe => Maybe::success((object) ['message' => "Resource(s) not found"]);

        // 3. Execute
        $this->notificationManager->checkStatusOfSentNotifications();

        // 4. Verify
        $updatedAttempt = $this->notificationAttemptRepository->getById($attempt->id);
        $this->assertEquals(NotificationAttemptStatus::NotFound, $updatedAttempt->status);
        $this->assertNull($updatedAttempt->checkError, "checkError should be null");
    }

    public function testCheck_CheckFailed_NonsensicalResponse()
    {
        // 1. Prepare Data
        $msg1 = $this->createTestEventWithMsg('Patient Check 2', 'Msg 2', NotificationMsgStatus::Sent, new DateTime('-1 hour'));
        $attempt = $this->createTestAttempt($msg1, NotificationAttemptStatus::Sent, 2357);

        // 2. Setup Mock
        $this->smsGwService->handler = fn(): Maybe => Maybe::success("WTF");

        // 3. Execute
        $this->notificationManager->checkStatusOfSentNotifications();

        // 4. Verify
        $updatedAttempt = $this->notificationAttemptRepository->getById($attempt->id);
        $this->assertEquals(NotificationAttemptStatus::CheckError, $updatedAttempt->status);
        $this->assertNotNull($updatedAttempt->checkError, "checkError should not be null");
        $this->assertStringContainsString('Unexpected response: "WTF"', $updatedAttempt->checkError);
    }

    public function testCheck_Reserved_ReschedulesCheck()
    {
        $GW_ID = 12345;

        // 1. Prepare Data
        $msg = $this->createTestEventWithMsg('Reserved Patient', 'Msg 1', NotificationMsgStatus::Sent, new DateTime('-1 hour'));
        // createTestAttempt already sets checkAt to "now" which is eligible for listToCheck()
        $attempt = $this->createTestAttempt($msg, NotificationAttemptStatus::Sent, $GW_ID);

        // 2. Setup Mock
        $this->smsGwService->handler = fn() => Maybe::success([
            (object) [
                'status' => 'reserved',
                'error_code' => null,
                'sending_date' => '2023-01-01 10:00:00',
                'delivery_date' => null,
            ]
        ]);

        // 3. Execute
        $this->notificationManager->checkStatusOfSentNotifications();

        // 4. Verify
        $updatedAttempt = $this->notificationAttemptRepository->getById($attempt->id);

        // Status should be Queued (as per MsgCheckResponseHandler logic for reschedule)
        $this->assertEquals(NotificationAttemptStatus::Queued, $updatedAttempt->status);

        // check_at should be in the future (1 minute later for the first check)
        $this->assertMaxTimeDiffInSeconds(new DateTime('+1 minutes'), $updatedAttempt->checkAt, 10);

        // gw_check_status_history should contain "reserved"
        $this->assertEquals('reserved', $updatedAttempt->gwCheckStatusHistory);

        // NotificationMsg should still be Sent
        $updatedMsg = $this->notificationMsgRepository->getById($msg->id);
        $this->assertEquals(NotificationMsgStatus::Sent, $updatedMsg->status);
    }

    public function testCheck_Reserved_ReschedulesCheck_SecondTime()
    {
        $GW_ID = 12345;

        // 1. Prepare Data
        $msg = $this->createTestEventWithMsg('Reserved Patient 2', 'Msg 1', NotificationMsgStatus::Sent, new DateTime('-1 hour'));

        $attempt = $this->createTestAttempt($msg, NotificationAttemptStatus::Sent, $GW_ID);

        // We need to set history so determineCheckNo returns 2
        $this->database->table('notification_attempt')
            ->where('id', $attempt->id)
            ->update(['gw_check_status_history' => 'reserved']);

        // 2. Setup Mock
        $this->smsGwService->handler = fn() => Maybe::success([
            (object) [
                'status' => 'reserved',
                'error_code' => null,
                'sending_date' => '2023-01-01 10:00:00',
                'delivery_date' => null,
            ]
        ]);

        // 3. Execute
        $this->notificationManager->checkStatusOfSentNotifications();

        // 4. Verify
        $updatedAttempt = $this->notificationAttemptRepository->getById($attempt->id);

        $this->assertEquals(NotificationAttemptStatus::Queued, $updatedAttempt->status);

        // check_at should be in the future (around 2 minutes later)
        $this->assertMaxTimeDiffInSeconds(new DateTime('+2 minutes'), $updatedAttempt->checkAt, 10);

        // gw_check_status_history should contain "reserved,reserved"
        $this->assertEquals('reserved,reserved', $updatedAttempt->gwCheckStatusHistory);
    }

    // #################################
    // Helpers
    // #################################

    private function assertMaxTimeDiffInSeconds(DateTime $expected, DateTime $actual, int $maxDiffSeconds, ?string $msg = null): void
    {
        $diffInSeconds = abs($expected->getTimestamp() - $actual->getTimestamp());
        if (!$msg)
            $msg = "Time difference should be max $maxDiffSeconds seconds.\n" .
                "Expected: {$expected->format('Y-m-d H:i:s T')}\n" .
                "Actual: {$actual->format('Y-m-d H:i:s T')}";
        $this->assertLessThanOrEqual($maxDiffSeconds, $diffInSeconds, $msg);
    }
}