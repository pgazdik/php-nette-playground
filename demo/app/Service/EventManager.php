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
        $this->eventRepository->insert($event);

        // 2. Calculate scheduledAt
        // Logic: 7 days before appointment. If appointment is within 7 days, scheduledAt = now.
        // We work with Bratislava time because appointmentDate is in Bratislava time.
        
        $scheduledAt = (clone $event->appointmentDate)->modify('-7 days');
        
        $now = new DateTime('now', new DateTimeZone('Europe/Bratislava'));
        
        if ($scheduledAt < $now) {
            $scheduledAt = $now;
        }

        // 3. Create Text Notification
        $text = sprintf(
            "Hello %s, you have an appointment with %s on %s.",
            $event->patientName,
            $event->doctorName,
            $event->appointmentDate->format('Y-m-d H:i')
        );

        $notificationMsg = new NotificationMsg(
            eventId: $event->id,
            msgIndex: 1,
            notificationType: NotificationType::Main,
            mediaType: MediaType::Text,
            status: NotificationMsgStatus::Draft,
            text: $text,
            scheduledAt: $scheduledAt
        );

        $this->notificationMsgRepository->insert($notificationMsg);

        // 4. Create Image Notification if attachment exists
        if ($event->attachmentContent !== null) {
            $imageMsg = new NotificationMsg(
                eventId: $event->id,
                msgIndex: 2,
                notificationType: NotificationType::Main,
                mediaType: MediaType::Image,
                status: NotificationMsgStatus::Draft,
                text: '',
                scheduledAt: $scheduledAt,
            );
            $this->notificationMsgRepository->insert($imageMsg);
        }
    }

}
