<?php
namespace App\Service;

use App\Model\Entity\Event\Event;
use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Model\Entity\Event\NotificationType;
use DateTime;
use DateTimeZone;

class EventManager
{
    public function __construct(
        private EventRepository $eventRepository,
        private NotificationMsgRepository $notificationMsgRepository,
        private NotificationAttemptRepository $notificationAttemptRepository
    ) {
    }

    public function createEvent(Event $event): void
    {
        // 1. Create Event
        $eventId = $this->eventRepository->create($event);

        // 2. Calculate sendAt
        // Logic: 7 days before appointment. If appointment is within 7 days, sendAt = now.
        // We work with Bratislava time because appointmentDate is in Bratislava time.
        
        $sendAt = (clone $event->appointmentDate)->modify('-7 days');
        
        $now = new DateTime('now', new DateTimeZone('Europe/Bratislava'));
        
        if ($sendAt < $now) {
            $sendAt = $now;
        }

        // 3. Create Text Notification
        $text = sprintf(
            "Hello %s, you have an appointment with %s on %s.",
            $event->patientName,
            $event->doctorName,
            $event->appointmentDate->format('Y-m-d H:i')
        );

        $notificationMsg = new NotificationMsg(
            eventId: $eventId,
            msgIndex: 1,
            notificationType: NotificationType::Main,
            mediaType: MediaType::Text,
            status: NotificationMsgStatus::New,
            text: $text,
            sendAt: $sendAt
        );

        $this->notificationMsgRepository->create($notificationMsg);

        // 4. Create Image Notification if attachment exists
        if ($event->attachmentContent !== null) {
            $imageMsg = new NotificationMsg(
                eventId: $eventId,
                msgIndex: 2,
                notificationType: NotificationType::Main,
                mediaType: MediaType::Image,
                status: NotificationMsgStatus::New,
                text: '',
                sendAt: $sendAt,
            );
            $this->notificationMsgRepository->create($imageMsg);
        }
    }

    public function approveNotification(int $notificationId): void
    {
        $notificationMsg = $this->notificationMsgRepository->getById($notificationId);
        if (!$notificationMsg) {
            throw new \Exception("Notification not found");
        }

        $this->notificationMsgRepository->approveNotificationsForEvent($notificationMsg->eventId);

        $this->notificationAttemptRepository->create(NotificationAttempt::createFirstAttempt($notificationMsg));
    }
}
