<?php
namespace Tests\Service;

use DateTime;

use App\Model\Entity\Event\Event;
use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Model\Entity\Event\NotificationType;

use App\Service\EventRepository;
use App\Service\NotificationMsgRepository;
use App\Service\NotificationAttemptRepository;

use Nextras\Migrations\Engine\Runner;
use Nextras\Migrations\IDriver;
use Nextras\Migrations\IConfiguration;
use Nextras\Migrations\Printers\DevNull;

use Tests\Db\DbTestCase;

class EventDbTestCase extends DbTestCase
{

    protected static string $PHONE_NUMBER = '123';
    protected static string $DOCTOR_NAME = 'Dr. Test';
    protected static string $DOCTOR_ADDRESS = 'Address';

    protected EventRepository $eventRepository;
    protected NotificationMsgRepository $notificationMsgRepository;
    protected NotificationAttemptRepository $notificationAttemptRepository;

    protected IConfiguration $configuration;

    public function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = $this->container->getByType(EventRepository::class);
        $this->notificationMsgRepository = $this->container->getByType(NotificationMsgRepository::class);
        $this->notificationAttemptRepository = $this->container->getByType(NotificationAttemptRepository::class);

        $this->configuration = $this->container->getByType(IConfiguration::class);

        $this->initDb();
    }

    private function initDb(): void
    {
        $driver = $this->container->getByType(IDriver::class);

        $runner = new Runner($driver, new DevNull());
        $runner->run(Runner::MODE_RESET, $this->configuration);
    }

    //
    //
    //

    protected function fetchSingleEvent(): Event
    {
        $events = $this->eventRepository->listAll();
        $this->assertCount(1, $events, 'Expected exactly one event');
        return $events[0];
    }

    protected function createTestEvent(string $patientName, ?DateTime $appointmentDate = null): Event
    {
        return $this->createTestEventWithAttachment(
            $patientName,
            $appointmentDate ?? new DateTime('+1 day'),
            null,
            null,
            null
        );
    }

    protected function createTestEventWithMsg(string $patientName, string $text, NotificationMsgStatus $status, ?DateTime $scheduledAt = null): NotificationMsg
    {
        $event = $this->createTestEvent($patientName);
        $this->eventRepository->insert($event);

        $msg = $this->createNotificationMsg($event->id, 1, MediaType::Text, $text, $status, $scheduledAt ?? new DateTime());
        $this->notificationMsgRepository->insert($msg);

        return $msg;
    }

    protected function createTestAttempt(NotificationMsg $msg, NotificationAttemptStatus $status, ?int $gwId = null, ?DateTime $checkAt = null): NotificationAttempt
    {
        $attempt = new NotificationAttempt(
            notificationMsgId: $msg->id,
            attemptNo: 1,
            status: $status,
            gwId: $gwId,
            msg: $msg,
            checkAt: $checkAt ?? new DateTime()
        );
        $this->notificationAttemptRepository->insert($attempt);
        return $attempt;
    }

    protected function createTestEventWithAttachment(
        string $patientName,
        DateTime $appointmentDate,
        ?string $attachmentContent,
        ?string $attachmentName,
        ?string $attachmentType
    ): Event {
        return new Event(
            patientName: $patientName,
            phoneNumber: self::$PHONE_NUMBER,
            doctorName: self::$DOCTOR_NAME,
            doctorAddress: self::$DOCTOR_ADDRESS,
            appointmentDate: $appointmentDate,
            attachmentContent: $attachmentContent,
            attachmentName: $attachmentName,
            attachmentType: $attachmentType
        );
    }

    protected function createTextNotificationMsg(int $eventId, int $index, string $text, NotificationMsgStatus $status, DateTime $scheduledAt = new DateTime()): NotificationMsg
    {
        return $this->createNotificationMsg($eventId, $index, MediaType::Text, $text, $status, $scheduledAt);
    }

    protected function createNotificationMsg(int $eventId, int $index, MediaType $mediaType, string $text, NotificationMsgStatus $status, DateTime $scheduledAt = new DateTime()): NotificationMsg
    {
        return new NotificationMsg(
            eventId: $eventId,
            notificationType: NotificationType::Main,
            mediaType: $mediaType,
            status: $status,
            text: $text,
            scheduledAt: $scheduledAt,
            msgIndex: $index,
        );
    }


}
