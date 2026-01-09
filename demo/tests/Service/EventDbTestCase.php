<?php
namespace Tests\Service;

use DateTime;

use App\Model\Entity\Event\Event;
use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Model\Entity\Event\NotificationType;

use App\Service\EventRepository;

use Tests\Db\DbTestCase;

class EventDbTestCase extends DbTestCase
{

    protected static string $PHONE_NUMBER = '123';
    protected static string $DOCTOR_NAME = 'Dr. Test';
    protected static string $DOCTOR_ADDRESS = 'Address';

    protected EventRepository $eventRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = $this->container->getByType(EventRepository::class);
    }

    protected function createEventTable(): void
    {
        $this->database->query('
            CREATE TABLE `event` (
              `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `patient_name` varchar(255) NOT NULL,
              `phone_number` varchar(50) NOT NULL,
              `doctor_name` varchar(255) NOT NULL,
              `doctor_address` text NOT NULL,
              `appointment_date` datetime NOT NULL,

              `attachment_content` LONGBLOB NULL,
              `attachment_name` varchar(255) NULL,
              `attachment_type` varchar(100) NULL,

              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');

        $this->database->table('event')->delete();
    }

    protected function createNotificationTable(): void
    {
        $this->database->query('
            CREATE TABLE `notification_msg` (
              `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `event_id` int(11) NOT NULL,
              `msg_index` int(11) NOT NULL,
              `notification_type` varchar(20) NOT NULL,
              `media_type` varchar(20) NOT NULL,
              `status` varchar(20) NOT NULL,
              `text` text NOT NULL,
              `send_at` datetime NOT NULL,
              `approved_at` datetime NULL,

              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');
    }

    protected function createNotificationAttemptTable(): void
    {
        $this->database->query('
            CREATE TABLE `notification_attempt` (
              `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `notification_msg_id` int(11) NOT NULL,
              `attempt_no` int(11) NOT NULL,
              `send_at` datetime NOT NULL,
              `status` varchar(20) NOT NULL,
              `sending_error` varchar(255) NULL,
              `gw_id` int(11) NULL,
              `gw_send_status` varchar(255) NULL,
              `gw_check_status` varchar(255) NULL,
              `gw_error_code` int(11) NULL,
              `gw_send_date` datetime NULL,
              `gw_delivery_date` datetime NULL,

              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              FOREIGN KEY (`notification_msg_id`) REFERENCES `notification_msg` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');
    }

    protected function dropAllTables(): void
    {
        $this->database->query("DROP TABLE IF EXISTS `notification_attempt`");
        $this->database->query("DROP TABLE IF EXISTS `notification_msg`");
        $this->database->query("DROP TABLE IF EXISTS `event`");
    }

    //
    //
    //

    protected function fetchSingleEvent(): Event
    {
        $events = $this->eventRepository->getAll();
        $this->assertCount(1, $events, 'Expected exactly one event');
        return $events[0];
    }

    protected function createTestEvent(string $patientName, DateTime $appointmentDate): Event
    {
        return $this->createTestEventWithAttachment(
            $patientName,
            $appointmentDate,
            null,
            null,
            null
        );
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

    protected function createTextNotificationMsg(int $eventId, int $index, string $text, NotificationMsgStatus $status, DateTime $sendAt = new DateTime()): NotificationMsg
    {
        return  $this->createNotificationMsg($eventId, $index, MediaType::Text, $text, $status, $sendAt);
    }

    protected function createNotificationMsg(int $eventId, int $index, MediaType $mediaType,string $text, NotificationMsgStatus $status, DateTime $sendAt = new DateTime()): NotificationMsg
    {
        return new NotificationMsg(
            eventId: $eventId,
            notificationType: NotificationType::Main,
            mediaType: $mediaType,
            status: $status,
            text: $text,
            sendAt: $sendAt,
            msgIndex: $index,
        );
    }


}
