<?php
namespace Tests\Service;

use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Model\Entity\Event\NotificationType;
use App\Service\NotificationAttemptRepository;
use App\Service\NotificationMsgRepository;
use DateTime;

class NotificationAttemptRepositoryTest extends EventDbTestCase
{
    private NotificationMsgRepository $notificationMsgRepository;
    private NotificationAttemptRepository $notificationAttemptRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->dropAllTables();
        $this->createEventTable();
        $this->createNotificationTable();
        $this->createNotificationAttemptTable();

        $this->notificationMsgRepository = $this->container->getByType(NotificationMsgRepository::class);
        $this->notificationAttemptRepository = $this->container->getByType(NotificationAttemptRepository::class);
    }

    public function test_ListToSend_1stMsgEligible()
    {
        $pastDate = new DateTime('-1 day');

        // Create Event 1 with 1 Message, 1 Failed Attempt and 1 Scheduled Attempt
        $event1 = $this->createTestEvent('Event 1', new DateTime('+1 day'));
        $eventId1 = $this->eventRepository->create($event1);

        $msg = $this->createTextNotificationMsg($eventId1, 1, "First Msg", NotificationMsgStatus::Scheduled, $pastDate);
        $msg->id = $this->notificationMsgRepository->create($msg);

        $firstAttempt = NotificationAttempt::createFirstAttempt($msg);
        $firstAttempt->status = NotificationAttemptStatus::Failed;
        $this->notificationAttemptRepository->create($firstAttempt);

        $secondAttempt = clone $firstAttempt;
        $secondAttempt->attemptNo = 2;
        $secondAttempt->status = NotificationAttemptStatus::Scheduled;
        $this->notificationAttemptRepository->create($secondAttempt);

        //  Actual test

        /** @var NotificationAttempt[] $results */
        $results = $this->notificationAttemptRepository->listToSend();

        $this->assertNotEmpty($results);
        $this->assertCount(1, $results);

        $attempt = $results[0];

        $this->assertEquals(2, $attempt->attemptNo);

        $this->assertNotNull($attempt->msg);
        $this->assertEquals("First Msg", $attempt->msg->text);
    }


}
