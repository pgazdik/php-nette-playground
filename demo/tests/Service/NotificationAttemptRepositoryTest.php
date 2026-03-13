<?php
namespace Tests\Service;

use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Model\Entity\Event\NotificationMsgStatus;
use DateTime;

class NotificationAttemptRepositoryTest extends EventDbTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_GetNextAttemptNo()
    {
        $pastDate = new DateTime('-1 day');

        // Create Event 1 with 1 Message, 1 Failed Attempt
        $event = $this->createTestEvent('Event 1', new DateTime('+1 day'));
        $this->eventRepository->insert($event);

        $msg = $this->createTextNotificationMsg($event->id, 1, "Msg", NotificationMsgStatus::Scheduled, $pastDate);
        $this->notificationMsgRepository->insert($msg);

        // First attempt (failed)
        $firstAttempt = new NotificationAttempt(
            notificationMsgId: $msg->id,
            attemptNo: 1,
            status: NotificationAttemptStatus::Failed
        );
        $this->notificationAttemptRepository->insert($firstAttempt);

        // Check next attempt no
        $nextAttemptNo = $this->notificationAttemptRepository->getNextAttemptNo($msg->id);
        $this->assertEquals(2, $nextAttemptNo);

        // Add second attempt
        $secondAttempt = new NotificationAttempt(
            notificationMsgId: $msg->id,
            attemptNo: 2,
            status: NotificationAttemptStatus::Failed
        );
        $this->notificationAttemptRepository->insert($secondAttempt);

        // Check next attempt no
        $nextAttemptNo = $this->notificationAttemptRepository->getNextAttemptNo($msg->id);
        $this->assertEquals(3, $nextAttemptNo);
    }

}
